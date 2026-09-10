<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\PaprikaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaprikaController extends Controller
{
    private PaprikaService $paprikaService;

    public function __construct()
    {
        $this->paprikaService = new PaprikaService;
    }

    public function snapAccessTokenB2B(Request $request): JsonResponse
    {
        try {
            LogHelper::sendLog('Paprika SNAP B2B Access Token Request', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]);

            return $this->paprikaService->generateB2BAccessToken($request);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return response()->json([
                'responseCode' => '5007300',
                'responseMessage' => 'General Error [' . $ex->getMessage() . ']',
            ], 500);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            LogHelper::sendLog('Paprika Webhook', $request->all());
            $callback = $this->paprikaService->callback($request);
            DB::commit();

            return $callback;
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function callback(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            LogHelper::sendLog('Paprika Callback', $request->all());
            $callback = $this->paprikaService->callback($request);
            DB::commit();

            return $callback;
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }
}
