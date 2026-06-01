<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\DuitkuService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OtherController extends Controller
{
    private DuitkuService $duitkuService;
    public function __construct(DuitkuService $duitkuService)
    {
        $this->duitkuService = $duitkuService;
    }

    public function duitkuEncrpyt(Request $request): JsonResponse
    {
        try {
            $result = $this->duitkuService->duitkuEncrpyt($request);
            return ResponseHelper::successResponse($result, 'Success Create DuitkuEncrpyt');
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function duitkuPaymentSync(Request $request): JsonResponse
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
