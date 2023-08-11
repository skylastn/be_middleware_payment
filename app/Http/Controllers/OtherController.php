<?php

namespace App\Http\Controllers;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use Exception;
use Illuminate\Http\Request;

class OtherController extends Controller
{
    public function duitkuEncrpyt(Request $request)
    {
        try {
            if (!env('APP_DEBUG')) {
                throw new Exception("Apps on Production Mode");
            }
            $dateNow = date("Y-m-d H:i:s");
            $result['date']         = $dateNow;
            $result['request']      = $request->all();
            $result['signature']    = hash('sha256', $request->merchantCode . $request->paymentAmount . $dateNow . $request->apiKey);

            if ($request->type == 'callback') {
                $result['signature']    = hash('sha256', $request->merchantCode . $request->paymentAmount . $request->merchantOrderId . $request->apiKey);
            }
            return ResponseHelper::successResponse($result, 'Success Create DuitkuEncrpyt');
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
