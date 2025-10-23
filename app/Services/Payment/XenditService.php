<?php

namespace App\Services\Payment;

use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Models\Order;
use App\Models\PaymentRepository;
use App\Models\Project;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;

class XenditService
{
    private InvoiceApi $apiInstance;
    private PaymentRepositoryService $paymentRepositoryService;
    public function __construct()
    {
        $this->apiInstance = new InvoiceApi();
        $this->paymentRepositoryService = new PaymentRepositoryService();
    }

    public function getPaymentRepo(string $mode, $id): ?PaymentRepository
    {
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }
        return $this->paymentRepositoryService->getByPaymentGatewayKey('xendit', $mode);
    }

    public function order(Request $request, Project $project): array
    {
        $paymentRepo = $this->getPaymentRepo($request->mode, $request->paymentRepositoryId);
        if (!FormatHelper::isNotEmpty($paymentRepo)) {
            throw new Exception('Payment Repository Not Found');
        }
        $secretKey = $paymentRepo->getValue()['xendit_secretkey'] ?? '';
        $urlSuccess = $request->returnUrl ?? '';
        // Xendit::setApiKey($secretKey);
        Configuration::setXenditKey($secretKey);

        $invID = DB::table('orders')->whereDate('created_at', Carbon::today())->orderBy('created_at', 'desc')->first();
        $invIDCount                 = substr($invID->id ?? 00000, -5);
        $invID_num                  = (int)$invIDCount + 1;
        $merchantOrderId            = date("Ymd") . "-" . str_pad($invID_num, 5, '0', STR_PAD_LEFT);
        // return $request->req;
        $req['id']                  = $merchantOrderId;
        $req['reference']           = $project->type . '-' . $request->merchantOrderId;
        $req['type']                = $project->type;
        $req['mode']                = $request->mode ?? "sandbox";
        $req['payment_method']      = "";

        $expired                    = ($request->expiryPeriod ?? 0) * 60;


        $params = [
            'external_id' => $req['reference'] ?? $project->type . '-' . $req['id'],
            'amount' => $request->paymentAmount ?? 0,
            'description' => $request->productDetails ?? "Payment",
            'invoice_duration' => $expired,
            // 'payer_email' => $request->firstName,
            // 'customer' => [
            //     'given_names' => $request->firstName ?? "Artho",
            //     'surname' => $request->lastName ?? '',
            //     'email' => $request->email ??'artho@gmail.com',
            //     'mobile_number' => '+6287774441111',
            //     'addresses' => [
            //         [
            //             'city' => '',
            //             'country' => 'Indonesia',
            //             'postal_code' => '',
            //             'state' => '',
            //             'street_line1' => '',
            //             'street_line2' => ''
            //         ]
            //     ]
            // ],
            // 'customer_notification_preference' => [
            //     'invoice_created' => [
            //         'whatsapp',
            //         // 'sms',
            //         'email',
            //         // 'viber'
            //     ],
            //     'invoice_reminder' => [
            //         'whatsapp',
            //         'sms',
            //         'email',
            //         'viber'
            //     ],
            //     'invoice_paid' => [
            //         'whatsapp',
            //         // 'sms',
            //         'email',
            //         // 'viber'
            //     ],
            //     'invoice_expired' => [
            //         'whatsapp',
            //         // 'sms',
            //         'email',
            //         // 'viber'
            //     ]
            // ],
            'success_redirect_url' => $urlSuccess,
            'failure_redirect_url' => $project->callback,
            'currency' => 'IDR',
            // 'items' => [
            //     [
            //         'name' => 'Payment',
            //         'quantity' => 1,
            //         'price' => $request->paymentAmount ?? 0,
            //         'category' => 'Payment',
            //         'url' => $cek['data']->callback
            //     ]
            // ],
            // 'fees' => [
            //     [
            //         'type' => 'ADMIN',
            //         'value' => 5000
            //     ]
            // ],
            'reminder_time' => 1,
        ];

        $create_invoice_request = new CreateInvoiceRequest($params);
        $req['request']             = json_encode($params);
        $order                      = Order::create($req);
        $order                      = Order::findOrFailCustom($order->id);
        LogHelper::sendLog(
            'Request Order Xendit',
            json_encode($order),
            $project->id,
            'request_order_xendit'
        );

        $createInvoice = $this->apiInstance->createInvoice($create_invoice_request);
        $result = json_encode($createInvoice);
        LogHelper::sendLog(
            'Response Order Xendit',
            json_encode($createInvoice),
            $project->id,
            'response_order_xendit'
        );
        $order->setResponse($result);
        $order->setUrl($createInvoice['invoice_url']);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->save();

        $response['message']    = "Success Create Order";
        $response['link']       = $createInvoice['invoice_url'];
        $response['data']       = $createInvoice;
        return $response;
    }

    public function callback(Request $request): Order
    {
        $order = Order::where("reference", $request->external_id)->orderBy('id', 'DESC')->first();
        if (!$order) {
            throw new Exception('Order not found');
        }
        $order = Order::findOrFailCustom($order->id);
        $paymentRepo = $this->getPaymentRepo($order->mode, $order->getPaymentRepositoryId());

        $xenditToken = $paymentRepo->getValue()['xendit_tokencallback'] ?? '';
        $reqHeaders = getallheaders();
        $incomingTokenXendit = isset($reqHeaders['X-Callback-Token']) ? $reqHeaders['X-Callback-Token'] : "";

        if ($xenditToken != $incomingTokenXendit) {
            throw new Exception('You are not permitted perform this action');
        }

        $order->setCallback(json_encode($request->all()));
        $order->setStatus($request->status);
        $order->setPaymentMethod($request->payment_channel);
        $order->save();

        $split              = explode("-", $request->external_id);
        $project            = Project::where("type", $split[0])->first();
        LogHelper::sendLog(
            'Callback Xendit',
            json_encode($request->all()),
            $project->id,
            'callback_order_xendit'
        );
        if ($request->status == "PAID") {
            $params['merchantOrderId']  = $split[1] . "-" . $split[2];
            $params['paymentCode']      = $order->payment_method;
            $params['resultCode']       = "00";
            $callback                   = RequestHelper::sendCallback($project->value, $params, $project->callback);
        }
        $order->refresh();
        return $order;
    }
}
