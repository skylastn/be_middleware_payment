<?php

namespace App\Services\Payment;

use App\Enums\DirectFlow;
use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Enums\SurchargeMode;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Stripe\Token;
use Stripe\Webhook;

class StripeService
{
    private PaymentRepositoryService $paymentRepositoryService;
    private OrderHistoryService $orderHistoryService;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
        $this->orderHistoryService = new OrderHistoryService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? (env('IS_DEFAULT_SANDBOX', false) ? PaymentModeType::prod->value : PaymentModeType::sandbox->value);
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }

        return $this->paymentRepositoryService->getByPaymentGatewayKey('stripe', $modeValue);
    }

    public function order(Request $request, Project $project): array
    {
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentRepositoryId);
        if (! FormatHelper::isNotEmpty($paymentRepo)) {
            throw new Exception('Payment Repository Not Found');
        }
        $secretKey = $paymentRepo->getValue()['stripe_secretkey'] ?? '';
        if (empty($secretKey)) {
            throw new Exception('Stripe Secret Key Not Configured');
        }

        Stripe::setApiKey($secretKey);

        $publishableKey = $paymentRepo->getValue()['stripe_publishablekey'] ?? null;

        $merchantOrderId = OrderIdGenerator::generate();
        $currency = strtolower($request->currency ?? 'idr');
        $rawAmount = (float) ($request->paymentAmount ?? 0);
        $amount = $this->toSmallestUnit($rawAmount, $currency);
        $productName = $request->productDetails ?? 'Payment';

        $req['id'] = $merchantOrderId;
        $req['reference'] = $project->type . '-' . $request->merchantOrderId;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->input('paymentMethod') ?? $request->input('payment_method') ?? '';
        $req['amount'] = $rawAmount;
        $customerName = trim(($request->firstName ?? '') . ' ' . ($request->lastName ?? ''));
        $req['name'] = $customerName ?: ($request->customerVaName ?? $request->name ?? null);
        $req['status'] = OrderStatus::PENDING->value;
        $req['return_url'] = $request->input('returnUrl') ?: $request->input('return_url') ?: $project->callback;

        $surcharge = $this->resolveSurcharge($request, $paymentRepo, $rawAmount, $currency);

        $isDirect = $this->isDirectCardFlow($request);
        $cardProvidedAtCreate = $isDirect && ($this->hasRawCardData($request) || str_starts_with((string)($request->input('payment_method') ?? ''), 'pm_'));

        if ($isDirect) {
            // Direct PaymentIntent / credit card flow.
            // Only entered when flow matches a DirectFlow value (direct, credit_card, card, payment_intent, etc).
            // Supports sending card data at create time (auto confirm), or create then /stripe/confirm later.
            $chargeAmount = $surcharge['mode'] === 'middleware_calc' && $surcharge['fee_amount'] > 0
                ? $surcharge['gross_amount']
                : $amount;

            $meta = [
                'reference' => $req['reference'],
                'order_id' => $req['id'],
                'surcharge_mode' => $surcharge['mode'],
            ];

            if ($surcharge['mode'] === 'middleware_calc' && $surcharge['fee_amount'] > 0) {
                $meta['base_amount'] = (string) $amount;
                $meta['fee_amount'] = (string) $surcharge['fee_amount'];
                $meta['gross_amount'] = (string) $surcharge['gross_amount'];
            }

            $params = [
                'amount' => $chargeAmount,
                'currency' => $currency,
                'payment_method_types' => ['card'],
                'description' => $productName,
                'metadata' => $meta,
            ];

            // For request logging, never include raw card details
            $req['request'] = json_encode($params);
            $order = Order::create($req);
            $order = Order::findOrFailCustom($order->id);
            LogHelper::sendLog(
                'Request Order Stripe',
                json_encode($order),
                $project->id,
                'request_order_stripe'
            );

            $createParams = $params;
            if ($cardProvidedAtCreate) {
                $createParams['confirm'] = true;
                $pmSpec = $this->getPaymentMethodSpec($request);
                if (is_string($pmSpec)) {
                    $createParams['payment_method'] = $pmSpec;
                } else {
                    $createParams['payment_method_data'] = $pmSpec['payment_method_data'];
                }
            }

            try {
                $paymentIntent = PaymentIntent::create($createParams);
            } catch (CardException $e) {
                // Payment failed case (CardException): update status to FAILED (as per rule: only for payment failed/error/expired),
                // log the error, commit so FAILED is persisted, then re-throw ("throw again the log").
                LogHelper::sendErrorLog($e);
                $order->setStatus(OrderStatus::FAILED);
                $order->setPaymentMethod('card');
                $order->setNotes('Card error: ' . $e->getMessage());
                $order->save();
                DB::commit();

                throw $e;
            } catch (ApiErrorException $e) {
                LogHelper::sendErrorLog($e);

                $msg = $e->getMessage();
                if (stripos($msg, 'raw card') !== false || stripos($msg, 'unsafe') !== false || stripos($msg, 'Sending credit card numbers') !== false) {
                    throw new Exception(
                        'Stripe rejected raw card data: ' . $msg . ' ' .
                        'Solution: send a test token instead (e.g. include "token": "tok_visa" in your request). ' .
                        'See https://stripe.com/docs/testing for test tokens. ' .
                        'To enable raw card testing: https://support.stripe.com/questions/enabling-access-to-raw-card-data-apis .'
                    );
                }

                throw $e;
            }

            $result = json_encode($paymentIntent->toArray());
            LogHelper::sendLog(
                'Response Order Stripe',
                $result,
                $project->id,
                'response_order_stripe'
            );
            $order->setResponse($result);
            $order->setUrl($paymentIntent->client_secret ?? null);
            $order->setValue(null);
            $order->setPaymentRepositoryId($paymentRepo->id);
            $order->save();

            $this->orderHistoryService->log(
                $order,
                OrderStatus::PENDING,
                'ORDER_CREATE_STRIPE',
                'Order created with status PENDING (Direct Card Flow)',
                $params,
                null
            );

            if ($cardProvidedAtCreate && $paymentIntent->status === 'succeeded') {
                $order->setStatus(OrderStatus::SUCCESS);
                $order->setPaymentMethod('card');
                $order->save();
                // Merchant callback sent before controller commit (see note in confirm path).
                $this->sendSuccessCallback($order, $project);
            }

            $response['message'] = 'Success Create Order';
            $response['reference'] = $req['reference'];
            $response['merchantOrderId'] = $request->input('merchantOrderId');
            $response['status'] = $paymentIntent->status;
            if ($publishableKey) {
                $response['publishable_key'] = $publishableKey;
            }
            if ($surcharge['mode'] === 'middleware_calc' && $surcharge['fee_amount'] > 0) {
                $response['surcharge'] = [
                    'mode' => $surcharge['mode'],
                    'base_amount' => $amount,
                    'fee_amount' => $surcharge['fee_amount'],
                    'gross_amount' => $surcharge['gross_amount'],
                    'fee_label' => $surcharge['label'],
                ];
            } elseif ($surcharge['mode'] === 'stripe_automatic') {
                $response['surcharge'] = [
                    'mode' => 'stripe_automatic',
                ];
            }
            $response['data'] = $paymentIntent->toArray();
            if (! empty($paymentIntent->client_secret)) {
                $response['client_secret'] = $paymentIntent->client_secret;
            }

            return $response;
        }

        // Hosted Checkout flow (returns "link" for redirect).
        // This is the default when "flow" is not sent (or does not match any DirectFlow value).
        // Simple "URL checkout" like Duitku snap / payment page.
        $successUrl = $request->input('returnUrl') ?: $project->callback;
        $cancelUrl = $project->callback ?: $successUrl;

        if (! FormatHelper::isNotEmpty($successUrl) && ! FormatHelper::isNotEmpty($cancelUrl)) {
            throw new Exception('Project callback URL or returnUrl is required for Stripe hosted checkout (URL flow)');
        }

        // Best practice for Stripe hosted checkout: include the placeholder so you can verify the session on return
        if (!empty($successUrl) && strpos($successUrl, '{CHECKOUT_SESSION_ID}') === false) {
            $glue = strpos($successUrl, '?') !== false ? '&' : '?';
            $successUrl .= $glue . 'session_id={CHECKOUT_SESSION_ID}';
        }

        // paymentMethod determines which Stripe methods to show on the hosted page.
        //   'all' or empty → show all common enabled methods (card, fpx, grabpay, link)
        //   specific value  → only that method (e.g. 'card', 'fpx', 'grabpay')
        $paymentMethod = strtolower(trim($request->input('paymentMethod') ?? $request->input('payment_method') ?? ''));

        $lineItems = [
            [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => $productName,
                    ],
                ],
                'quantity' => 1,
            ],
        ];

        if ($surcharge['mode'] === 'middleware_calc' && $surcharge['fee_amount'] > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $surcharge['fee_amount'],
                    'product_data' => [
                        'name' => $surcharge['label'],
                    ],
                ],
                'quantity' => 1,
            ];
        }

        $sessionMeta = [
            'reference' => $req['reference'],
            'order_id' => $req['id'],
            'surcharge_mode' => $surcharge['mode'],
        ];

        if ($surcharge['mode'] === 'middleware_calc' && $surcharge['fee_amount'] > 0) {
            $sessionMeta['base_amount'] = (string) $amount;
            $sessionMeta['fee_amount'] = (string) $surcharge['fee_amount'];
            $sessionMeta['gross_amount'] = (string) $surcharge['gross_amount'];
        }

        $params = [
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $sessionMeta,
        ];

        if ($surcharge['mode'] === 'stripe_automatic') {
            $params['automatic_surcharge'] = [
                'enabled' => true,
            ];
        }

        if (empty($paymentMethod) || $paymentMethod === 'all') {
            $params['payment_method_types'] = ['card', 'fpx', 'grabpay', 'link'];
        } else {
            $params['payment_method_types'] = [$paymentMethod];
        }

        // Pass common customer fields so the Stripe hosted page is pre-filled (like Duitku customerDetail)
        // This gives a better "snap / payment page" experience without forcing direct card handling.
        $customerEmail = $request->input('email');
        if ($customerEmail) {
            $params['customer_email'] = $customerEmail;
        }

        // Ask Stripe Checkout to collect billing details (address etc.)
        $params['billing_address_collection'] = 'required';

        // Enable phone collection on the hosted page
        $params['phone_number_collection'] = ['enabled' => true];

        // Expiry (similar to expiryPeriod in Duitku)
        $expiryMinutes = (int) ($request->input('expiryPeriod') ?? 0);
        if ($expiryMinutes > 0) {
            $expiresAt = time() + ($expiryMinutes * 60);
            // Stripe Checkout sessions typically support expires_at between ~30min and 24h
            if ($expiresAt > time() + 1800) {
                $params['expires_at'] = $expiresAt;
            }
        }

        // Enrich metadata with customer info for your backend records
        $custMeta = [];
        if ($fn = $request->input('firstName')) $custMeta['first_name'] = $fn;
        if ($ln = $request->input('lastName')) $custMeta['last_name'] = $ln;
        if ($ph = $request->input('phone')) $custMeta['phone'] = $ph;
        if ($ad = $request->input('address')) $custMeta['address'] = $ad;
        if (!empty($custMeta)) {
            $params['metadata']['customer'] = json_encode($custMeta);
        }

        // Also put description on the resulting PaymentIntent (consistent with direct flow)
        $params['payment_intent_data'] = [
            'description' => $productName,
            'metadata' => $sessionMeta,
        ];

        $req['request'] = json_encode($params);
        $order = Order::create($req);
        $order = Order::findOrFailCustom($order->id);
        LogHelper::sendLog(
            'Request Order Stripe',
            json_encode($order),
            $project->id,
            'request_order_stripe'
        );

        $checkoutSession = StripeCheckoutSession::create($params);
        $result = json_encode($checkoutSession->toArray());
        LogHelper::sendLog(
            'Response Order Stripe',
            $result,
            $project->id,
            'response_order_stripe'
        );
        $order->setResponse($result);
        $order->setUrl($checkoutSession->url ?? null);
        $order->setValue(null);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            OrderStatus::PENDING,
            'ORDER_CREATE_STRIPE',
            'Order created with status PENDING (Hosted Checkout Flow)',
            $params,
            null
        );

        $response['message'] = 'Success Create Order';
        $response['reference'] = $req['reference'];
        $response['merchantOrderId'] = $request->input('merchantOrderId');
        $response['status'] = $checkoutSession->status;
        if ($publishableKey) {
            $response['publishable_key'] = $publishableKey;
        }
        if ($surcharge['mode'] === 'middleware_calc' && $surcharge['fee_amount'] > 0) {
            $response['surcharge'] = [
                'mode' => $surcharge['mode'],
                'base_amount' => $amount,
                'fee_amount' => $surcharge['fee_amount'],
                'gross_amount' => $surcharge['gross_amount'],
                'fee_label' => $surcharge['label'],
            ];
        } elseif ($surcharge['mode'] === 'stripe_automatic') {
            $response['surcharge'] = [
                'mode' => 'stripe_automatic',
            ];
        }
        $response['link'] = $checkoutSession->url;
        $response['data'] = $checkoutSession->toArray();

        return $response;
    }

    public function callback(Request $request): Order
    {
        $sigHeader = $request->header('Stripe-Signature');
        if (empty($sigHeader)) {
            throw new Exception('Missing Stripe-Signature header');
        }

        $payload = $request->getContent();

        $event = null;
        $paymentRepos = PaymentRepository::whereHas('payment_gateway', function ($q) {
            $q->where('key', 'stripe');
        })->get();

        foreach ($paymentRepos as $paymentRepo) {
            $webhookSecret = $paymentRepo->getValue()['stripe_webhooksecret'] ?? '';
            if (empty($webhookSecret)) {
                continue;
            }
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
                break;
            } catch (SignatureVerificationException $e) {
                continue;
            }
        }

        if (! $event) {
            throw new Exception('Invalid Stripe webhook signature');
        }

        $order = null;

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $reference = $session->metadata->reference ?? '';
            if (empty($reference)) {
                throw new Exception('Missing reference in session metadata');
            }

            $order = Order::where('reference', $reference)->orderBy('id', 'DESC')->first();
            if (! $order) {
                throw new Exception('Order not found');
            }
            $order = Order::findOrFailCustom($order->id);

            $split = explode('-', $reference);
            $project = Project::where('type', $split[0])->first();

            LogHelper::sendLog(
                'Callback Stripe',
                json_encode($event->toArray()),
                $project->id,
                'callback_order_stripe'
            );

            $order->setCallback(json_encode($event->toArray()));
            $alreadySucceeded = $order->getStatus()?->isSuccess();
            if (! $alreadySucceeded) {
                $order->setStatus(OrderStatus::SUCCESS);
                $order->setPaymentMethod($session->payment_method_types[0] ?? 'card');
                $order->save();

                $params = [
                    'merchantOrderId' => $order->getMerchantOrderId(),
                    'paymentCode' => $order->payment_method,
                    'resultCode' => '00',
                ];
                SendMerchantCallback::dispatch(
                    $project->value,
                    $params,
                    $project->callback
                )->afterCommit();
            } else {
                $order->save();
            }

            $order->refresh();
        } elseif ($event->type === 'checkout.session.expired') {
            $session = $event->data->object;
            $reference = $session->metadata->reference ?? '';
            if (! empty($reference)) {
                $order = Order::where('reference', $reference)->orderBy('id', 'DESC')->first();
                if ($order) {
                    $order = Order::findOrFailCustom($order->id);
                    $order->setCallback(json_encode($event->toArray()));
                    $order->setStatus(OrderStatus::EXPIRED);
                    $order->save();
                }
            }
        } elseif (str_starts_with($event->type, 'payment_intent.')) {
            $pi = $event->data->object;
            $reference = $pi->metadata->reference ?? '';
            if (! empty($reference)) {
                $matchedOrder = Order::where('reference', $reference)->orderBy('id', 'DESC')->first();
                if ($matchedOrder) {
                    $matchedOrder = Order::findOrFailCustom($matchedOrder->id);
                    $matchedOrder->setCallback(json_encode($event->toArray()));
                    $isSuccessEvent = $event->type === 'payment_intent.succeeded';
                    $alreadySucceeded = $matchedOrder->getStatus()?->isSuccess();

                    $previousStatus = $matchedOrder->status;
                    if ($isSuccessEvent) {
                        if (! $alreadySucceeded) {
                            $matchedOrder->setStatus(OrderStatus::SUCCESS);
                            $matchedOrder->setPaymentMethod('card');
                        }
                    } elseif (in_array($event->type, ['payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
                        $matchedOrder->setStatus(OrderStatus::FAILED);
                    }
                    $matchedOrder->save();

                    $this->orderHistoryService->log(
                        $matchedOrder,
                        $matchedOrder->status ?? OrderStatus::SUCCESS,
                        'WEBHOOK_STRIPE',
                        "Stripe webhook event processed: {$event->type}",
                        $event->toArray(),
                        $previousStatus
                    );

                    // Send merchant callback after save (and controller will commit after this method)
                    // Skip if already succeeded (e.g. confirmStripe already processed success + sent the merchant callback)
                    if ($isSuccessEvent && ! $alreadySucceeded) {
                        $split = explode('-', $reference);
                        $proj = Project::where('type', $split[0] ?? '')->first();
                        if ($proj && $proj->callback) {
                            $p = ['merchantOrderId' => $matchedOrder->getMerchantOrderId(), 'paymentCode' => 'card', 'resultCode' => '00'];
                            SendMerchantCallback::dispatch(
                                $proj->value,
                                $p,
                                $proj->callback
                            )->afterCommit();
                        }
                    }
                    $order = $matchedOrder;
                }
            }
        }

        if (FormatHelper::isNotEmpty($order)) {
            SendNotificationJob::dispatch($order->getReference() ?? $reference);
        }

        return $order ?? throw new Exception('Order not found for event');
    }

    /**
     * Check current transaction status directly from Stripe (supports both Checkout Sessions and Payment Intents).
     * Used by /order/checkOrderStatus for Stripe projects.
     */
    public function checkStatus(Order $order): \stdClass
    {
        $paymentRepo = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());
        $secretKey = $paymentRepo->getValue()['stripe_secretkey'] ?? '';
        if (empty($secretKey)) {
            throw new Exception('Stripe Secret Key Not Configured');
        }

        Stripe::setApiKey($secretKey);

        $responseData = json_decode($order->getResponse() ?? '{}', true) ?: [];
        $objectType = $responseData['object'] ?? null;

        if ($objectType === 'checkout.session') {
            $sessionId = $responseData['id'] ?? null;
            if ($sessionId) {
                $session = StripeCheckoutSession::retrieve($sessionId, ['expand' => ['payment_intent']]);
                $data = $session->toArray();
                return json_decode(json_encode($data));
            }
        }

        if ($objectType === 'payment_intent') {
            $piId = $responseData['id'] ?? null;
            if ($piId) {
                $pi = PaymentIntent::retrieve($piId);
                $data = $pi->toArray();
                return json_decode(json_encode($data));
            }
        }

        throw new Exception('Unable to determine Stripe object (checkout.session or payment_intent) for status check on this order');
    }

    /**
     * Confirm/send card data (pm_xxx or tok_xxx) for a previously created *direct* PaymentIntent order.
     * Call this after /order/create when you used flow=direct (the credit card / direct flow).
     *
     * To get the hosted URL checkout instead (returns "link" like Duitku), do NOT send flow=direct
     * (or send flow=checkout etc.). No confirm step needed for the link flow. Default when flow not sent.
     *
     * In production: always create the pm_ or token on the CLIENT (Stripe.js / mobile SDK) using
     * your publishable key. Never send raw card numbers from your own servers in live mode.
     *
     * Error handling: log error then re-throw ("throw again the log") so that the controller's catch
     * performs rollback + failedResponse. Only update the order status for payment failed/error/expired
     * cases (e.g. CardException sets FAILED). Other errors (ApiErrorException) do not touch order status.
     */
    public function confirmCardPayment(Request $request, Project $project): array
    {
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentRepositoryId);
        if (! FormatHelper::isNotEmpty($paymentRepo)) {
            throw new Exception('Payment Repository Not Found');
        }
        $secretKey = $paymentRepo->getValue()['stripe_secretkey'] ?? '';
        if (empty($secretKey)) {
            throw new Exception('Stripe Secret Key Not Configured');
        }

        Stripe::setApiKey($secretKey);

        $reference = $request->reference;
        if (empty($reference) && $request->merchantOrderId) {
            $reference = $project->type . '-' . $request->merchantOrderId;
        }
        if (empty($reference)) {
            throw new Exception('reference or merchantOrderId is required');
        }

        $order = Order::where('reference', $reference)->orderBy('id', 'DESC')->first();
        if (! $order) {
            throw new Exception('Order not found');
        }
        $order = Order::findOrFailCustom($order->id);

        $split = explode('-', $reference);
        if (($split[0] ?? '') !== $project->type) {
            throw new Exception('Order project type does not match');
        }

        $resp = json_decode($order->getResponse() ?? '{}', true) ?: [];
        if (($resp['object'] ?? '') !== 'payment_intent') {
            throw new Exception('This order was not created for direct card confirmation. Send "flow": "direct" (or "credit_card", "card", "payment_intent", etc.) on /order/create to use the credit card direct flow + confirm. If no flow (or flow=checkout), you get the hosted "link" instead (no confirm needed).');
        }

        $piId = $resp['id'] ?? null;
        if (! $piId) {
            throw new Exception('PaymentIntent ID missing from order response');
        }

        if (! $this->canProvidePaymentMethod($request)) {
            throw new Exception('Provide payment_method (pm_xxx), token (tok_visa etc), or card details (card_number + exp + cvc)');
        }

        LogHelper::sendLog(
            'Request Confirm Stripe Card',
            json_encode(['reference' => $reference]),
            $project->id,
            'request_confirm_stripe'
        );

        try {
            $paymentIntent = PaymentIntent::retrieve($piId);
            $pmSpec = $this->getPaymentMethodSpec($request);
            $confirmPayload = is_string($pmSpec) ? ['payment_method' => $pmSpec] : $pmSpec;

            $confirmedIntent = $paymentIntent->confirm($confirmPayload);

            $resultJson = json_encode($confirmedIntent->toArray());
            LogHelper::sendLog(
                'Response Confirm Stripe Card',
                $resultJson,
                $project->id,
                'response_confirm_stripe'
            );

            $order->setResponse($resultJson);
            $order->setPaymentMethod('card');
            $previousStatus = $order->status;
            $newStatus = $this->mapStripePiStatusToOrder($confirmedIntent->status);
            if ($newStatus) {
                $order->setStatus($newStatus);
            }
            $order->save();

            if ($newStatus) {
                $this->orderHistoryService->log(
                    $order,
                    $newStatus,
                    'DIRECT_CARD_CONFIRM',
                    "Stripe PaymentIntent confirmed: {$confirmedIntent->status}",
                    $confirmedIntent->toArray(),
                    $previousStatus
                );
            }

            if ($confirmedIntent->status === 'succeeded') {
                // Merchant callback (project's webhook) is sent here, right after save but before the controller's DB::commit().
                // The HTTP send to merchant happens while the tx is still open. In practice this is fine (very short window),
                // but if the merchant immediately queries back the order status, they might see pre-commit state depending on DB.
                $this->sendSuccessCallback($order, $project);
            }

            return [
                'message' => 'Stripe card confirmation processed',
                'reference' => $reference,
                'status' => $confirmedIntent->status,
                'data' => $confirmedIntent->toArray(),
            ];
        } catch (CardException $e) {
            // Payment failed (card declined etc). Update status to FAILED (as per "update status only for payment failed/error/expired"),
            // log, commit the change so it persists, then re-throw ("throw again the log") so controller's catch handles
            // the error response (and would rollback but since we committed the failure record, it's ok).
            LogHelper::sendErrorLog($e);
            $latest = null;
            try {
                $latest = PaymentIntent::retrieve($piId);
                $order->setResponse(json_encode($latest->toArray()));
            } catch (\Exception $ignore) {
            }
            $order->setStatus(OrderStatus::FAILED);
            $order->setPaymentMethod('card');
            $order->setNotes('Card error: ' . $e->getMessage());
            $order->save();
            DB::commit();

            throw $e;
        } catch (ApiErrorException $e) {
            // Non-payment-failure Stripe errors: just log and re-throw. Do not update order status.
            LogHelper::sendErrorLog($e);

            $msg = $e->getMessage();
            if (stripos($msg, 'raw card') !== false || stripos($msg, 'unsafe') !== false || stripos($msg, 'Sending credit card numbers') !== false) {
                // Special case for Stripe's raw card restriction (common when testing with direct card_number instead of tokens)
                throw new Exception(
                    'Stripe rejected raw card data: ' . $msg . ' ' .
                    'Solution: send a test token instead, e.g. {"token": "tok_visa"} (or real tok_ created via Stripe.js on client). ' .
                    'For raw cards in testing, request access at https://support.stripe.com/questions/enabling-access-to-raw-card-data-apis . ' .
                    'Never send raw PANs in production.'
                );
            }

            throw $e;
        }
    }

    // ------------------------------------------------------------
    // Private helpers for direct card / PaymentIntent flows
    // ------------------------------------------------------------

    /**
     * Simple explicit decision:
     * - If flow matches any DirectFlow (direct, credit_card, card, payment_intent, etc.) → use direct credit card / PaymentIntent flow
     *   (returns client_secret, supports sending card data at create or via /stripe/confirm later)
     * - If flow is not sent (or any other value like checkout, link, hosted, or omitted) → use hosted link flow
     *   (returns "link" for redirect, like Duitku snap / payment page)
     *
     * See DirectFlow enum for the full list of accepted values and legacy aliases.
     */
    private function isDirectCardFlow(Request $request): bool
    {
        $flow = $request->input('stripe.flow')
            ?? $request->input('stripe.payment_flow')
            ?? $request->input('flow')
            ?? $request->input('payment_flow')
            ?? '';

        return DirectFlow::isDirect($flow);
    }

    private function hasRawCardData(Request $request): bool
    {
        if ($request->filled('card_number') || $request->filled('cardNumber') || $request->filled('number') || $request->filled('stripe.card_number')) {
            return true;
        }
        $card = $request->input('stripe.card') ?? $request->input('card');
        if (is_array($card) && ! empty($card['number'] ?? $card['card_number'] ?? null)) {
            return true;
        }
        return false;
    }

    private function hasCardToken(Request $request): bool
    {
        return $this->extractTokenFromRequest($request) !== null;
    }

    private function extractTokenFromRequest(Request $request): ?string
    {
        $token = $request->input('stripe.token')
            ?? $request->input('stripe.card_token')
            ?? $request->input('token')
            ?? $request->input('card_token')
            ?? $request->input('tok');

        if (is_string($token) && str_starts_with(trim($token), 'tok_')) {
            return trim($token);
        }

        $card = $request->input('stripe.card') ?? $request->input('card', []);
        if (is_array($card) && ! empty($card['token'] ?? null)) {
            $t = (string) $card['token'];
            if (str_starts_with(trim($t), 'tok_')) {
                return trim($t);
            }
        }

        return null;
    }

    private function canProvidePaymentMethod(Request $request): bool
    {
        $pmField = (string) ($request->input('payment_method') ?? $request->input('paymentMethod') ?? '');
        if (str_starts_with(trim($pmField), 'pm_') || str_starts_with(trim($pmField), 'tok_')) {
            return true;
        }
        return $this->hasRawCardData($request) || $this->hasCardToken($request);
    }

    private function getPaymentMethodSpec(Request $request): array|string
    {
        $pm = $request->input('payment_method') ?? $request->input('paymentMethod') ?? $request->input('payment_method_id') ?? $request->input('pm_id');
        if (is_string($pm)) {
            $pm = trim($pm);
            if (str_starts_with($pm, 'pm_')) {
                return $pm;
            }
            if (str_starts_with($pm, 'tok_')) {
                // Client provided a token (from Stripe.js or test token).
                // We normalize it to a PaymentMethod server-side so we can attach billing_details.
                return $this->createPaymentMethodFromToken($pm, $request);
            }
        }

        if ($this->hasRawCardData($request)) {
            // User sent raw card data (card_number etc) to this endpoint.
            // We create the token server-side using the secret key (via /tokens),
            // then create a PaymentMethod from it (to support billing_details).
            // This fulfills "token created via endpoint".
            // NOTE: This will often fail with Stripe's "raw card numbers ... unsafe" error (exactly the one you reported)
            // unless your Stripe account has raw card data APIs explicitly enabled.
            // Strongly prefer sending "token": "tok_xxx" instead.
            // Raw card data touches this server -> PCI implications for the operator.
            $tokenId = $this->createTokenFromRawCard($request);
            return $this->createPaymentMethodFromToken($tokenId, $request);
        }

        if ($this->hasCardToken($request)) {
            // token provided via dedicated fields
            $tokenId = $this->extractTokenFromRequest($request);
            return $this->createPaymentMethodFromToken($tokenId, $request);
        }

        throw new Exception('payment_method (pm_xxx or tok_xxx), or card details required');
    }

    /**
     * Create a Stripe Token from raw card details sent to this endpoint.
     * This is "token created via endpoint" when the caller only wants to send raw card data.
     */
    private function createTokenFromRawCard(Request $request): string
    {
        $card = $request->input('card', []);

        $number = $request->input('card_number')
            ?? $request->input('cardNumber')
            ?? $request->input('number')
            ?? ($card['number'] ?? $card['card_number'] ?? null);

        $expMonth = $request->input('card_exp_month')
            ?? $request->input('cardExpMonth')
            ?? $request->input('exp_month')
            ?? $request->input('expiryMonth')
            ?? ($card['exp_month'] ?? $card['expiryMonth'] ?? null);

        $expYear = $request->input('card_exp_year')
            ?? $request->input('cardExpYear')
            ?? $request->input('exp_year')
            ?? $request->input('expiryYear')
            ?? ($card['exp_year'] ?? $card['expiryYear'] ?? null);

        $cvc = $request->input('card_cvc')
            ?? $request->input('cardCvc')
            ?? $request->input('cvc')
            ?? $request->input('cvv')
            ?? ($card['cvc'] ?? $card['cvv'] ?? null);

        $name = $request->input('card_holder_name') ?? $request->input('name') ?? $request->input('firstName') ?? null;

        if (empty($number) || empty($expMonth) || empty($expYear) || empty($cvc)) {
            throw new Exception('Incomplete card data for server-side tokenization.');
        }

        $tokenCard = [
            'number' => (string) $number,
            'exp_month' => (int) $expMonth,
            'exp_year' => (int) $expYear,
            'cvc' => (string) $cvc,
        ];

        if ($name) {
            $tokenCard['name'] = $name;
        }

        $token = Token::create([
            'card' => $tokenCard,
        ]);

        return $token->id;
    }

    /**
     * Create a PaymentMethod from a token (either provided by client or created from raw).
     * This allows attaching billing_details even when starting from a token.
     */
    private function createPaymentMethodFromToken(string $tokenId, Request $request): string
    {
        $name = $request->input('card_holder_name') ?? $request->input('name') ?? $request->input('firstName') ?? null;
        $email = $request->input('email');
        $phone = $request->input('phone');

        $billing = [];
        if ($name) $billing['name'] = $name;
        if ($email) $billing['email'] = $email;
        if ($phone) $billing['phone'] = $phone;

        // Optional: support more address fields if sent as address_line1 etc.
        $address = [];
        foreach (['line1', 'line2', 'city', 'state', 'postal_code', 'country'] as $f) {
            $val = $request->input('address_' . $f) ?? $request->input('address.' . $f);
            if ($val) $address[$f] = (string)$val;
        }
        if (!empty($address)) {
            $billing['address'] = $address;
        }

        $pm = PaymentMethod::create([
            'type' => 'card',
            'card' => ['token' => $tokenId],
            'billing_details' => $billing ?: null,
        ]);

        return $pm->id;
    }
    /**
     * Queue the merchant callback notification.
     * Uses afterCommit() so the job is only dispatched after the surrounding DB transaction succeeds.
     * The actual HTTP send is handled by SendMerchantCallback job (retries on failure).
     */
    private function sendSuccessCallback(Order $order, Project $project): void
    {
        $merchantOrderId = $order->getMerchantOrderId();

        $params = [
            'merchantOrderId' => $merchantOrderId,
            'paymentCode' => $order->payment_method ?: 'card',
            'resultCode' => '00',
        ];

        LogHelper::sendLog(
            'Stripe Success Callback',
            json_encode($params),
            $project->id,
            'callback_order_stripe'
        );

        // Dispatch to queue so the HTTP callback doesn't block the request/transaction.
        // Using afterCommit() ensures it only queues after the DB transaction succeeds.
        SendMerchantCallback::dispatch(
            $project->value,
            $params,
            $project->callback
        )->afterCommit();
    }

    private function mapStripePiStatusToOrder(string $piStatus): ?OrderStatus
    {
        return match ($piStatus) {
            'succeeded' => OrderStatus::SUCCESS,
            'canceled' => OrderStatus::EXPIRED,
            'requires_payment_method', 'requires_confirmation', 'requires_action', 'processing' => OrderStatus::PENDING,
            default => OrderStatus::FAILED,
        };
    }

    private function resolveSurcharge(Request $request, PaymentRepository $paymentRepo, int $rawAmount, string $currency): array
    {
        $repoValue = $paymentRepo->getValue() ?? [];

        $rawMode = $request->input('stripe.surcharge_mode')
            ?? $request->input('stripe.surchargeMode')
            ?? $request->input('surchargeMode')
            ?? $request->input('surcharge_mode')
            ?? ($repoValue['surcharge_mode'] ?? SurchargeMode::MIDDLEWARE_CALC->value);

        $surchargeMode = SurchargeMode::fromName($rawMode) ?? SurchargeMode::MIDDLEWARE_CALC;

        if ($surchargeMode->isStripeAutomatic()) {
            return [
                'mode' => $surchargeMode->value,
                'enum' => $surchargeMode,
                'fee_amount_raw' => 0,
                'fee_amount' => 0,
                'gross_amount' => $this->toSmallestUnit($rawAmount, $currency),
                'label' => '',
            ];
        }

        if ($surchargeMode->isMiddlewareCalc()) {
            $defaultCurrencyRates = [
                'myr' => ['percent' => 3.0, 'fixed' => 1.00],
                'idr' => ['percent' => 2.9, 'fixed' => 2000],
                'usd' => ['percent' => 2.9, 'fixed' => 0.30],
                'sgd' => ['percent' => 3.4, 'fixed' => 0.50],
            ];
            $currDefaults = $defaultCurrencyRates[strtolower($currency)] ?? ['percent' => 2.9, 'fixed' => 0];

            $percent = (float) (
                $request->input('stripe.surcharge_percent')
                ?? $request->input('stripe.surchargePercent')
                ?? $request->input('surchargePercent')
                ?? $request->input('surcharge_percent')
                ?? ($repoValue['surcharge_percent'] ?? $currDefaults['percent'])
            );
            $fixed = (float) (
                $request->input('stripe.surcharge_fixed')
                ?? $request->input('stripe.surchargeFixed')
                ?? $request->input('surchargeFixed')
                ?? $request->input('surcharge_fixed')
                ?? ($repoValue['surcharge_fixed'] ?? $currDefaults['fixed'])
            );
            $label = (string) (
                $request->input('stripe.surcharge_label')
                ?? $request->input('stripe.surchargeLabel')
                ?? $request->input('surchargeLabel')
                ?? $request->input('surcharge_label')
                ?? ($repoValue['surcharge_label'] ?? 'Processing Fee')
            );

            if ($percent > 0 || $fixed > 0) {
                $p = $percent / 100.0;
                if ($p < 1.0) {
                    $grossRaw = (int) ceil(($rawAmount + $fixed) / (1.0 - $p));
                    $feeRaw = max(0, $grossRaw - $rawAmount);

                    $feeSmallest = $this->toSmallestUnit($feeRaw, $currency);
                    $baseSmallest = $this->toSmallestUnit($rawAmount, $currency);

                    return [
                        'mode' => $surchargeMode->value,
                        'enum' => $surchargeMode,
                        'fee_amount_raw' => $feeRaw,
                        'fee_amount' => $feeSmallest,
                        'gross_amount' => $baseSmallest + $feeSmallest,
                        'label' => $label,
                        'percent' => $percent,
                        'fixed' => $fixed,
                    ];
                }
            }
        }

        return [
            'mode' => SurchargeMode::NONE->value,
            'enum' => SurchargeMode::NONE,
            'fee_amount_raw' => 0,
            'fee_amount' => 0,
            'gross_amount' => $this->toSmallestUnit($rawAmount, $currency),
            'label' => '',
        ];
    }

    /**
     * Convert a human-readable amount (e.g. 150 for RM150) into Stripe's required
     * smallest currency unit (e.g. 15000 for RM150.00).
     *
     * Stripe always expects the amount in the currency's minor unit (sen, cents, etc.),
     * except for zero-decimal currencies.
     */
    private function toSmallestUnit(float|int $amount, string $currency): int
    {
        // Zero-decimal currencies on Stripe (no minor unit)
        // See: https://stripe.com/docs/currencies#zero-decimal
        $zeroDecimalCurrencies = [
            'bif',
            'clp',
            'djf',
            'gnf',
            'jpy',
            'kmf',
            'krw',
            'mga',
            'pyg',
            'rwf',
            'ugx',
            'vnd',
            'vuv',
            'xaf',
            'xof',
            'xpf',
        ];

        $currency = strtolower(trim($currency));

        if (in_array($currency, $zeroDecimalCurrencies, true)) {
            return (int) round($amount);
        }

        // All other currencies (including MYR, IDR, USD, SGD, etc.) use 2 decimal places
        // IDR is treated by Stripe as having a minor unit for API purposes.
        return (int) round($amount * 100);
    }

    /**
     * @param PaymentRepository $repository
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function fetchHistory(PaymentRepository $repository, array $filters = []): array
    {
        $config = is_array($repository->value) ? $repository->value : (json_decode((string) $repository->value, true) ?: []);
        $secretKey = $config['stripe_secretkey'] ?? null;
        if (! $secretKey) {
            throw new Exception('Missing stripe_secretkey in repository configuration');
        }

        Stripe::setApiKey($secretKey);

        $limit = (int) ($filters['limit'] ?? $filters['per_page'] ?? 10);
        $params = ['limit' => min($limit, 100)];
        if (! empty($filters['starting_after'])) {
            $params['starting_after'] = $filters['starting_after'];
        }
        if (! empty($filters['ending_before'])) {
            $params['ending_before'] = $filters['ending_before'];
        }
        if (! empty($filters['start_date'])) {
            $params['created']['gte'] = Carbon::parse($filters['start_date'])->startOfDay()->timestamp;
        }
        if (! empty($filters['end_date'])) {
            $params['created']['lte'] = Carbon::parse($filters['end_date'])->endOfDay()->timestamp;
        }

        $paymentIntents = PaymentIntent::all($params);

        $items = [];
        foreach ($paymentIntents->data as $pi) {
            $items[] = [
                'id' => $pi->id,
                'reference' => $pi->metadata->reference ?? $pi->id,
                'amount' => $pi->amount / (in_array(strtolower($pi->currency), ['idr', 'jpy', 'krw', 'vnd']) ? 1 : 100),
                'currency' => strtoupper($pi->currency),
                'status' => strtoupper($pi->status),
                'payment_method' => implode(', ', $pi->payment_method_types ?? ['card']),
                'customer' => $pi->receipt_email ?? $pi->customer ?? '-',
                'created_at' => Carbon::createFromTimestamp($pi->created)->toDateTimeString(),
                'raw' => $pi->toArray(),
            ];
        }

        return [
            'gateway' => 'Stripe',
            'has_more' => $paymentIntents->has_more,
            'total' => count($items),
            'items' => $items,
        ];
    }
}
