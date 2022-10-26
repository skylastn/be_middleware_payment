<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\ProjectController;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Xendit\Xendit;
use Vendor\autoload;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try{
            $cek        = (new ProjectController)->checkKey();
            if(!$cek['status']){
                return response()->json($cek, 403);
            }
            $page       = $request->page ?? "0";
            $limit      = $request->limit ?? "10";
            $response['message'] = "Success Get Order";
            $response['data']   = Order::forPage($page,$limit)->where('type', $cek['data']->type)->get();
            return response()->json($response, 200);
        }catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
        
    }

    public function store(Request $request)
    {   
        try{
            // return $request->all();
            $dateNow = date("Y-m-d H:i:s");
            $cek        = (new ProjectController)->checkKey();
            if(!$cek['status']){
                return response()->json($cek, 403);
            }

            DB::beginTransaction();
            $secretKey = Setting::where("key", "xendit_secretkey_sanbox")->first()->value;
            if($request->mode == "prod"){
                $secretKey = Setting::where("key", "xendit_secretkey_prod")->first()->value;
            }
            $urlSuccess = Setting::where("key", "url_success")->first()->value;
            Xendit::setApiKey($secretKey);

            $invID = DB::table('orders')->whereDate('created_at', Carbon::today())->orderBy('created_at', 'desc')->first();
            $invIDCount                 = substr($invID->id ?? 00000,-5);
            $invID_num                  = (int)$invIDCount+1;
            $merchantOrderId            = date("Ymd"). "-" . str_pad($invID_num, 5, '0', STR_PAD_LEFT);
            // return $request->req;
            $req['id']                  = $merchantOrderId;
            $req['reference']           = $cek['data']->type . '-' . $request->merchantOrderId;
            $req['type']                = $cek['data']->type;
            $req['mode']                = $request->mode ?? "sandbox";
            $req['payment_method']      = "";

            $expired                    = ($request->expiryPeriod ?? 0 ) * 60;
            // return $request;
            $params = [ 
                'external_id' => $req['reference'] ?? $cek['data']->type . '-' . $req['id'],
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
                'failure_redirect_url' => $cek['data']->callback,
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
                // ]
            ];
            
            $req['request']             = json_encode($params);
            $order                      = Order::create($req);

            $dataLog['key'] = "request_order";
            $dataLog['name'] = $req['request'];
            $this->storeLog($cek['data']->id, $dataLog);
            // return $params;
            $createInvoice = \Xendit\Invoice::create($params);
            $result = json_encode($createInvoice);
            $dataLog['key'] = "request_order";
            $dataLog['name'] = $result;
            $this->storeLog($cek['data']->id, $dataLog);
            
            
            DB::table('orders')
                ->where('id',$merchantOrderId)
                ->limit(1)
                ->update(
                [
                    'response'      => $result,
                    'updated_at'    => $dateNow,
                    "url"           => $createInvoice['invoice_url']
                ]
            );

            DB::commit();
            $response['message']    = "Success Create Order";
            $response['link']       = $createInvoice['invoice_url'];
            $response['data']       = $createInvoice;
            return response()->json($response, 200);

        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function Callback(Request $request)
    {   
        try{
            DB::beginTransaction();
            $order = Order::where("reference", $request->external_id)->orderBy('id', 'DESC')->first();
            if(!$order){
                return response()->json([
                    "message" => "Order not found"
                ], 200);
            }

            $xenditToken = Setting::where("key", "xendit_tokencallback_sanbox")->first()->value;
            if($order->mode == "prod"){
                $xenditToken = Setting::where("key", "xendit_tokencallback")->first()->value;
            }
            $reqHeaders = getallheaders();
            $incomingTokenXendit = isset($reqHeaders['X-Callback-Token']) ? $reqHeaders['X-Callback-Token'] : "";
            
            if($xenditToken != $incomingTokenXendit){
                return response()->json([
                    "message" => "You are not permitted perform this"
                ], 403);
            }

            
            $order->callback        = json_encode($request->all());
            $order->status          = $request->status;

            $order->payment_method  = $request->payment_channel;
            
            $order->save();
            


            $dataLog['key']     = "callback_order";
            $dataLog['name']    = $order->callback;
            $split              = explode("-", $request->external_id);
            $project            = Project::where("type", $split[0])->first();
            return response()->json([
                "data" => $split[0]
            ], 403);
            $this->storeLog($project->id, $dataLog);
            if($request->status == "PAID"){
                $params['merchantOrderId']  = $split[1] . "-" . $split[2];
                $params['paymentCode']      = $order->payment_method;
                $params['resultCode']       = "00";
                $callback                   = $this->sendCallback($project->value, $params , $project->callback);
            }
            
            DB::commit();
            $response['message']    = "Success Send Callback";
            $response['data']       = $callback;
            
            return response()->json($response, 200);

        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function sendCallback($token, $params, $urlCallback){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $urlCallback,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>json_encode($params),
            CURLOPT_HTTPHEADER => array(
                "Token: $token",
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        Log::info("Sending Request", [$urlCallback, $params, $token]);
        Log::info("Result Callback", [$response]);
        return json_decode($response);
        // echo $response;

    }

    public function storeLog($id, $dataLog)
    {   
        $dateNow                    = date("Y-m-d H:i:s");
        $dataLog['ip']              = $this->getClientIP();
        $dataLog['created_at']      = $dateNow;
        $dataLog['updated_at']      = $dateNow;
        $insertLog                  = DB::table('log__' . $id)->insert($dataLog);
        return $insertLog;
    }

    public function getClientIP() {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
           $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }


}
