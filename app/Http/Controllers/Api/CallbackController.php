<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\DuitkuService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\SPNPayService;
use App\Services\Payment\XenditService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CallbackController extends Controller
{
    private SPNPayService $spnPayService;
    private DuitkuService $duitkuService;
    private MidtransService $midtransService;
    private XenditService $xenditService;
    public function __construct()
    {
        $this->spnPayService = new SPNPayService();
        $this->duitkuService = new DuitkuService();
        $this->xenditService = new XenditService();
        $this->midtransService = new MidtransService();
    }

    public function callbackSPNPay(Request $request)
    {
        try {
            DB::beginTransaction();
            $callback = $this->spnPayService->callback($request);
            DB::commit();
            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackXendit(Request $request)
    {
        try {
            DB::beginTransaction();
            $callback = $this->xenditService->callback($request);
            DB::commit();
            return ResponseHelper::successResponse('Success Send Callback');
        } catch (\Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackMidtrans(Request $request)
    {
        try {
            DB::beginTransaction();
            $callback = $this->midtransService->callback($request);
            DB::commit();
            return ResponseHelper::successResponse('Success Send Callback');
        } catch (\Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackDuitku(Request $request)
    {
        try {
            DB::beginTransaction();
            $callback = $this->duitkuService->callback($request);
            DB::commit();
            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }
}
