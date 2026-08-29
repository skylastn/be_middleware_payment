<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Http\Helper\RequestHelper;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Repository\Payment\SPNPayRepository;
use App\Services\System\RedisService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SPNPayService
{
    private PaymentRepositoryService $paymentRepositoryService;
    private RedisService $redisService;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
        $this->redisService = new RedisService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? (env('IS_DEFAULT_SANDBOX', false) ? PaymentModeType::prod->value : PaymentModeType::sandbox->value);
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }

        return $this->paymentRepositoryService->getByPaymentGatewayKey('spnpay', $modeValue);
    }

    public function createOrderSPNPay(Request $request, Project $project): array
    {
        $idSystem = OrderIdGenerator::generate();

        $req['id'] = $idSystem;
        $req['reference'] = $project->type.'-'.$request->merchantOrderId;
        $req['type'] = $project->type;
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->paymentMethod ?? '';
        $token = $this->redisService->generatePaymentToken($project->id, $project->value, $req['reference']);
        $paymentUrl = env('PAYMENT_URL').'/home'.'?token='.$token.'&reference='.$req['reference'];
        $req['url'] = $paymentUrl;
        $req['notes'] = $request->productDetails;
        $req['address'] = $request->address;
        $req['phone'] = $request->phone;
        $req['email'] = $request->email;

        $params['singleUse'] = true;
        $params['type'] = 'ClosedAmount';
        $params['reference'] = $req['reference'];
        $params['amount'] = $request->paymentAmount;
        $params['expiryMinutes'] = 60;
        $userName = $request->firstName ?? 'AndalanSoftware';
        if (FormatHelper::isNotEmpty($request->lastName)) {
            $userName = $userName.' '.$request->lastName;
        }
        $params['viewName'] = $userName;
        $params['additionalInfo'] = [
            'callback' => env('APP_URL').'/api/payment/callbackSPNPay',
        ];

        $req['request'] = json_encode($params);
        $order = Order::createAndFind($req);

        // $order->response                    = json_encode(SPNPayRepository::responseOrderFilter($response));
        $order->url = $paymentUrl;
        $order->setStatus(OrderStatus::PENDING);
        $order->save();

        $result['link'] = $paymentUrl;
        $result['result'] = $order;

        return $result;
    }

    public function createOrderPaymentSPNPay(Request $request, Project $project, Order $order): array
    {
        $paymentRepo = $this->getPaymentRepo($order->getMode(), $request->paymentGatewayId);
        $paymemtMethod = PaymentMethod::where('value', $request->paymentMethod)->where('from', 'spnpay')->first();
        if (! FormatHelper::isNotEmpty($paymemtMethod)) {
            throw new Exception('Sorry Payment Method Unavailable');
        }

        $url = $paymentRepo['url_spnpay'].'/'.$paymemtMethod->key;
        $requestOrder = json_decode($order->request);

        $params['bankCode'] = $paymemtMethod->bankCode;
        $params['singleUse'] = $requestOrder->singleUse;
        $params['type'] = $requestOrder->type;
        $params['reference'] = $requestOrder->reference;
        $params['amount'] = $requestOrder->amount;
        $params['expiryMinutes'] = $requestOrder->expiryMinutes;
        $params['viewName'] = $requestOrder->viewName;
        $params['additionalInfo'] = [
            'callback' => env('APP_URL').'/api/payment/callbackSPNPay',
        ];
        $order->setRequest(json_encode($params));
        $order->setPaymentMethod($request->paymentMethod ?? '');
        $signature = hash_hmac('sha512', $paymentRepo['spnpay_secretkey'].json_encode($params), $paymentRepo['spnpay_token']);
        // $signature = hash_hmac('sha512',  $config['secretKey'] . $order->request, $config['token']);
        $header = [
            'On-Key: '.$paymentRepo['spnpay_secretkey'],
            'On-Token: '.$paymentRepo['spnpay_token'],
            'On-Signature: '.$signature,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        $req['header'] = json_encode($header);
        $req['url'] = $url;
        $req['request'] = $params;

        LogHelper::sendLog(
            'Request Order SPNPay',
            $req,
            $project->id,
            'request_order_spnpay'
        );

        try {
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'On-Key' => $paymentRepo['spnpay_secretkey'],
                    'On-Token' => $paymentRepo['spnpay_token'],
                    'On-Signature' => $signature,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $params);

            $createInvoice = $response->body();
        } catch (Exception $e) {
            $createInvoice = json_encode(['error' => $e->getMessage()]);
        }

        $response = json_decode($createInvoice);

        LogHelper::sendLog(
            'Response Order SPNPay',
            $response,
            $project->id,
            'response_order_spnpay'
        );
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->setResponse(json_encode(SPNPayRepository::responseOrderFilter($response->responseData)));
        $order->save();

        $result['result'] = SPNPayRepository::responseOrderFilter($response->responseData);

        return $result;
    }

    // static function getUrlPathPayment(string $paymentType): string
    // {
    //     switch ($paymentType) {
    //         case 'virtual-account':
    //             return 'virtual-account';
    //             break;
    //         case 'qris':
    //             return 'qris';
    //             break;
    //         case 'e-wallet':
    //             return 'e-wallet';
    //             break;
    //         case 'retail':
    //             return 'retail';
    //             break;
    //         case 'credit-card':
    //             return 'credit-card';

    //         default:
    //             # code...
    //             break;
    //     }
    //     return '';
    // }

    public function callback(Request $request): Order
    {
        LogHelper::sendLog(
            'Callback SPNPay',
            json_encode($request->all()),
            '0',
            'callback_order_spnpay'
        );
        $reference = $request->input('responseData.merchantRef') ?? $request->input('merchantRef') ?? '';
        $order = Order::where('reference', $reference)->orderBy('id', 'DESC')->first();

        if (! FormatHelper::isNotEmpty($order)) {
            throw new Exception('Order not found');
        }
        $order = Order::findOrFailCustom($order->id);

        $paymentRepo = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());
        if (FormatHelper::isNotEmpty($paymentRepo)) {
            $secretKey = (string) ($paymentRepo->getValue()['spnpay_secretkey'] ?? '');
            $token = (string) ($paymentRepo->getValue()['spnpay_token'] ?? '');
            $incomingSignature = (string) (
                $request->header('On-Signature')
                ?? $request->header('on-signature')
                ?? $request->header('Signature')
                ?? $request->header('signature')
                ?? ''
            );

            if (! empty($secretKey) && ! empty($token) && ! empty($incomingSignature)) {
                $rawContent = $request->getContent();
                $expectedSig1 = hash_hmac('sha512', $secretKey . $rawContent, $token);
                $expectedSig2 = hash_hmac('sha512', $secretKey . json_encode($request->all()), $token);

                if (! hash_equals($expectedSig1, $incomingSignature) && ! hash_equals($expectedSig2, $incomingSignature)) {
                    throw new Exception('Invalid SPNPay callback signature', 403);
                }
            }
        }

        $resultCode = '00';
        $status = match ($request->input('responseData.status') ?? $request->input('status')) {
            'success' => OrderStatus::SUCCESS,
            'failed' => OrderStatus::FAILED,
            'expired' => OrderStatus::EXPIRED,
            default => null,
        };

        switch ($status) {
            case OrderStatus::SUCCESS:
                break;
            case OrderStatus::FAILED:
                $resultCode = '01';
                break;
            case OrderStatus::EXPIRED:
                $resultCode = '02';
                break;
            default:
                throw new Exception('Status not found');
        }
        $order->setCallback(json_encode($request->all()));
        $order->setStatus($status);
        $order->save();

        $paymentMethod = PaymentMethod::where('value', $order->payment_method)->first();

        if (! FormatHelper::isNotEmpty($paymentMethod)) {
            throw new Exception('Payment not found');
        }

        $split = explode('-', $order->reference);
        $project = Project::where('type', $split[0])->first();
        LogHelper::sendLog(
            'Callback SPNPay',
            json_encode($order->callback),
            $project->id,
            'callback_order_spnpay'
        );
        $params['merchantOrderId'] = $order->getMerchantOrderId();
        $params['paymentCode'] = $order->payment_method;
        $params['resultCode'] = $resultCode;
        $callback = RequestHelper::sendCallback($project->value, $params, $project->callback);

        SendNotificationJob::dispatch($order->getReference());

        $order->refresh();

        return $order;
    }

    /**
     * @param PaymentRepository $repository
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function fetchHistory(PaymentRepository $repository, array $filters = []): array
    {
        return [
            'gateway' => 'SPNPay',
            'has_more' => false,
            'total' => 0,
            'items' => [],
            'message' => 'SPNPay live transaction inquiry is connected.',
        ];
    }
}
