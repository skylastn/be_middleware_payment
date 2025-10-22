<?php

namespace App\Http\Controllers;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\DuitkuService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OtherController extends Controller
{
    private DuitkuService $duitkuService;
    public function __construct(DuitkuService $duitkuService)
    {
        $this->duitkuService = $duitkuService;
    }

    public function duitkuEncrpyt(Request $request)
    {
        try {
            $result = $this->duitkuService->duitkuEncrpyt($request);
            return ResponseHelper::successResponse($result, 'Success Create DuitkuEncrpyt');
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function duitkuPaymentSync(Request $request)
    {
        try {
            DB::beginTransaction();
            $result = $this->duitkuService->duitkuPaymentSync($request);
            DB::commit();
            return ResponseHelper::successResponse($result, 'Success Sync Duitku');
        } catch (Exception $ex) {
            DB::rollBack();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
