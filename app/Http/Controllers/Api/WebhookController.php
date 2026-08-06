<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\DuitkuService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\SPNPayService;
use App\Services\Payment\StripeService;
use App\Services\Payment\XenditService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{

    public function __construct() {}

    public function webhookPaprika(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            LogHelper::sendLog('Paprika Webhook', $request->all());
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
