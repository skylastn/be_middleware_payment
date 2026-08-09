<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayoutGateway;
use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\DuitkuService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\PayoutService;
use App\Services\Payment\SPNPayService;
use App\Services\Payment\StripeService;
use App\Services\Payment\XenditService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CallbackController extends Controller
{
    private SPNPayService $spnPayService;

    private DuitkuService $duitkuService;

    private MidtransService $midtransService;

    private XenditService $xenditService;

    private StripeService $stripeService;

    private PayoutService $payoutService;

    public function __construct()
    {
        $this->spnPayService = new SPNPayService;
        $this->duitkuService = new DuitkuService;
        $this->xenditService = new XenditService;
        $this->midtransService = new MidtransService;
        $this->stripeService = new StripeService;
        $this->payoutService = new PayoutService;
    }

    public function callbackSPNPay(Request $request): JsonResponse
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

    public function callbackXendit(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $callback = $this->xenditService->callback($request);
            DB::commit();

            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackMidtrans(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $callback = $this->midtransService->callback($request);
            DB::commit();

            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackDuitku(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $callback = $this->duitkuService->callback($request);
            DB::commit();

            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            // return ResponseHelper::failedResponse('Internal Server Error');
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackStripe(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $callback = $this->stripeService->callback($request);
            DB::commit();

            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackPayoutStripe(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->payoutService->handleWebhook($request, PayoutGateway::Stripe);
            DB::commit();

            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callbackPaprika(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            LogHelper::sendLog('Paprika Callback', $request->all());
            // $callback = $this->stripeService->callback($request);
            DB::commit();

            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }
}
