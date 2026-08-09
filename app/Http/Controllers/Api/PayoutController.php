<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\PayoutService;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    private PayoutService $payoutService;

    private ProjectService $projectService;

    public function __construct()
    {
        $this->payoutService = new PayoutService;
        $this->projectService = new ProjectService;
    }

    public function create(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $project = $this->projectService->checkKey();

            $amount = (float) $request->input('amount');
            $currency = $request->input('currency', 'myr');
            $gateway = $request->input('gateway', 'stripe');
            $mode = $request->input('mode', 'sandbox');
            $bankDetails = $request->input('bank_details', []);
            $callerReference = $request->input('reference');
            $callbackUrl = $request->input('callback_url');

            if (empty($amount) || $amount <= 0) {
                throw new Exception('amount is required and must be greater than 0');
            }

            if (empty($bankDetails['bank_account'])) {
                throw new Exception('bank_details.bank_account is required');
            }

            $result = $this->payoutService->create(
                $project, $amount, $bankDetails, $gateway,
                $currency, $mode, $callerReference, $callbackUrl
            );

            DB::commit();

            return ResponseHelper::successResponse($result, 'Payout created successfully');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }
}
