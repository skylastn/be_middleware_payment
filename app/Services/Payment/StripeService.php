<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Http\Helper\RequestHelper;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Exception;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    private PaymentRepositoryService $paymentRepositoryService;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? PaymentModeType::sandbox->value;
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

        $merchantOrderId = OrderIdGenerator::generate();

        $req['id'] = $merchantOrderId;
        $req['reference'] = $project->type.'-'.$request->merchantOrderId;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = '';
        $req['status'] = OrderStatus::PENDING->value;

        $currency = strtolower($request->currency ?? 'idr');

        $params = [
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => (int) ($request->paymentAmount ?? 0),
                        'product_data' => [
                            'name' => $request->productDetails ?? 'Payment',
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_redirect_url' => $request->returnUrl ?? '',
            'cancel_redirect_url' => $project->callback,
            'metadata' => [
                'reference' => $req['reference'],
                'order_id' => $req['id'],
            ],
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
        $order->setUrl($checkoutSession->url);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->save();

        $response['message'] = 'Success Create Order';
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
            $order->setStatus(OrderStatus::SUCCESS);
            $order->setPaymentMethod($session->payment_method_types[0] ?? 'card');
            $order->save();

            $params['merchantOrderId'] = $split[1] ?? '';
            $params['paymentCode'] = $order->payment_method;
            $params['resultCode'] = '00';
            RequestHelper::sendCallback($project->value, $params, $project->callback);

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
        }

        return $order ?? throw new Exception('Order not found for event');
    }
}
