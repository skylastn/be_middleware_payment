<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Request\Payout\CreatePayoutRequest;
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

    public function create(CreatePayoutRequest $request): JsonResponse
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
