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
        try {
            $this->service->validatePayload($request, '73');
            $request->validate([
                'grantType' => ['required', 'string', 'in:client_credentials'],
                'additionalInfo' => ['sometimes', 'array'],
            ]);

            return ResponseHelper::payload($this->service->generateB2BAccessToken($request));
        } catch (Throwable $exception) {
            return $this->errorResponse($exception, '73');
        }
    }

    public function callback(Request $request): JsonResponse
    {
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
        if ($exception instanceof BankAgiException) {
            return ResponseHelper::payload([
                'responseCode' => $exception->responseCode,
                'responseMessage' => $exception->getMessage(),
            ], $exception->getCode());
        }

        if ($exception instanceof ValidationException) {
            $failed = $exception->validator->failed();
            $field = array_key_first($failed);
            $missing = isset($failed[$field]['Required']) || isset($failed[$field]['RequiredWith']);

            return ResponseHelper::payload([
                'responseCode' => '400'.$serviceCode.($missing ? '02' : '01'),
                'responseMessage' => ($missing ? 'Invalid Mandatory Field' : 'Invalid Field Format').' ['.$field.']',
            ], 400);
        }

        LogHelper::sendErrorLog($exception);

        return ResponseHelper::payload([
            'responseCode' => '500'.$serviceCode.'01',
            'responseMessage' => 'Internal Server Error',
        ], 500);
    }
}
