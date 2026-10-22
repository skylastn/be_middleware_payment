<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BankAgiException;
use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\BankAgiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BankAgiController extends Controller
{
    private BankAgiService $service;

    public function __construct(?BankAgiService $service = null)
    {
        $this->service = $service ?? new BankAgiService;
    }

    public function snapAccessTokenB2B(Request $request): JsonResponse
    {
        LogHelper::sendLog('Access Token AGI', [
            'headers' => [
                'X-CLIENT-KEY' => $request->header('X-CLIENT-KEY'),
                'X-TIMESTAMP' => $request->header('X-TIMESTAMP'),
                'X-SIGNATURE' => $request->header('X-SIGNATURE'),
                'Content-Type' => $request->header('Content-Type'),
            ],
            'body' => $request->all(),
            'raw' => mb_strimwidth($request->getContent(), 0, 2000, '...[truncated]'),
            'content_type' => $request->header('Content-Type'),
        ], '', 'request_access_token_agi');

        try {
            $this->service->validatePayload($request, '73');
            $request->validate([
                'grantType' => ['required', 'string', 'in:client_credentials'],
                'additionalInfo' => ['sometimes', 'array'],
            ]);

            $response = $this->service->generateB2BAccessToken($request);

            LogHelper::sendLog('Response Access Token AGI', $response, '', 'response_access_token_agi');

            return ResponseHelper::payload($response);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, '73');
        }
    }

    public function callback(Request $request): JsonResponse
    {
        // Log before any validation so rejected callbacks still leave a trace.
        LogHelper::sendLog('Callback AGI', [
            'headers' => [
                'X-PARTNER-ID' => $request->header('X-PARTNER-ID'),
                'X-TIMESTAMP' => $request->header('X-TIMESTAMP'),
                'X-EXTERNAL-ID' => $request->header('X-EXTERNAL-ID'),
                'CHANNEL-ID' => $request->header('CHANNEL-ID'),
                'X-SIGNATURE' => $request->header('X-SIGNATURE'),
                'Authorization' => $request->header('Authorization'),
            ],
            'body' => $request->all(),
            'raw' => mb_strimwidth($request->getContent(), 0, 2000, '...[truncated]'),
            'content_type' => $request->header('Content-Type'),
        ], '', 'request_callback_agi');

        try {
            $this->service->validatePayload($request, '19');
            $request->validate([
                'originalReferenceNo' => ['required', 'string', 'max:128'],
                'latestTransactionStatus' => ['required', 'string', 'in:00,01,02,03,04,05,06,07'],
                'additionalInfo' => ['required', 'array'],
                'additionalInfo.merchantId' => ['required', 'string', 'max:64'],
                'additionalInfo.merchantUser' => ['required', 'string', 'max:64'],
                'additionalInfo.billNumber' => ['required', 'string', 'max:128'],
                'amount' => ['sometimes', 'array'],
                'amount.value' => ['required_with:amount', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
                'amount.currency' => ['required_with:amount', 'in:IDR'],
            ]);

            DB::beginTransaction();
            $response = $this->service->callback($request);
            DB::commit();

            LogHelper::sendLog('Response Callback AGI', $response, '', 'response_callback_agi');

            return ResponseHelper::payload($response);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return $this->errorResponse($exception, '19');
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        return ResponseHelper::payload([
            'responseCode' => '5012500',
            'responseMessage' => 'AGI Virtual Account notifications are not supported. Virtual Account documentation is required.',
        ], 501);
    }

    private function errorResponse(Throwable $exception, string $serviceCode): JsonResponse
    {
        $action = match ($serviceCode) {
            '73' => 'access_token_agi',
            default => 'callback_agi',
        };
        $label = match ($serviceCode) {
            '73' => 'Access Token AGI',
            default => 'Callback AGI',
        };

        if ($exception instanceof BankAgiException) {
            $payload = [
                'responseCode' => $exception->responseCode,
                'responseMessage' => $exception->getMessage(),
            ];

            LogHelper::sendErrorLog($exception, [
                'service_code' => $serviceCode,
                'response_code' => $exception->responseCode,
                'response_message' => $exception->getMessage(),
            ], '', 'error_' . $action);

            LogHelper::sendLog('Response ' . $label, $payload, '', 'response_' . $action);

            return ResponseHelper::payload($payload, $exception->getCode());
        }

        if ($exception instanceof ValidationException) {
            $failed = $exception->validator->failed();
            $field = array_key_first($failed);
            $missing = isset($failed[$field]['Required']) || isset($failed[$field]['RequiredWith']);

            $payload = [
                'responseCode' => '400' . $serviceCode . ($missing ? '02' : '01'),
                'responseMessage' => ($missing ? 'Invalid Mandatory Field' : 'Invalid Field Format') . ' [' . $field . ']',
            ];

            LogHelper::sendErrorLog($exception, [
                'service_code' => $serviceCode,
                'failed_field' => $field,
                'errors' => $exception->errors(),
            ], '', 'error_' . $action);

            LogHelper::sendLog('Response ' . $label, $payload, '', 'response_' . $action);

            return ResponseHelper::payload($payload, 400);
        }

        $payload = [
            'responseCode' => '500' . $serviceCode . '01',
            'responseMessage' => 'Internal Server Error',
        ];

        LogHelper::sendErrorLog($exception, ['service_code' => $serviceCode], '', 'error_' . $action);
        LogHelper::sendLog('Response ' . $label, $payload, '', 'response_' . $action);

        return ResponseHelper::payload($payload, 500);
    }
}
