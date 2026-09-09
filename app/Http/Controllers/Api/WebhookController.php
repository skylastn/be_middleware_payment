<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\DuitkuService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\PaprikaService;
use App\Services\Payment\SPNPayService;
use App\Services\Payment\StripeService;
use App\Services\Payment\XenditService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    private SPNPayService $spnPayService;

    private DuitkuService $duitkuService;

    private MidtransService $midtransService;

    private XenditService $xenditService;

    private StripeService $stripeService;

    private PaprikaService $paprikaService;

    public function __construct()
    {
        $this->spnPayService = new SPNPayService;
        $this->duitkuService = new DuitkuService;
        $this->xenditService = new XenditService;
        $this->midtransService = new MidtransService;
        $this->stripeService = new StripeService;
        $this->paprikaService = new PaprikaService;
    }

    public function webhookPaprika(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            LogHelper::sendLog('Paprika Webhook', $request->all());
            // $callback = $this->stripeService->callback($request);
            $callback = $this->paprikaService->callback($request);
            DB::commit();

            return ResponseHelper::successResponse('Success Send Callback');
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }
}
