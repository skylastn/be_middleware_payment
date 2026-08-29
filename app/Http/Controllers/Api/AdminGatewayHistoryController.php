<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Entity\PaymentRepository;
use App\Services\Payment\GatewayHistoryService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGatewayHistoryController extends Controller
{
    public function __construct(private GatewayHistoryService $historyService = new GatewayHistoryService()) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $repositoryId = $request->query('payment_repository_id') ?: $request->query('repository_id');
            if (! $repositoryId || $repositoryId === 'all') {
                // Pick the first active payment repository if not specified
                $repository = PaymentRepository::query()->with('payment_gateway')->first();
            } else {
                $repository = PaymentRepository::query()->with('payment_gateway')->find($repositoryId);
            }

            if (! $repository) {
                return ResponseHelper::failedResponse('Payment repository not found', 'Not Found', 404);
            }

            $filters = [
                'limit' => (int) ($request->query('per_page', $request->query('perPage', 10))),
                'page' => (int) ($request->query('page', 1)),
                'start_date' => $request->query('start_date') ?: $request->query('startDate'),
                'end_date' => $request->query('end_date') ?: $request->query('endDate'),
                'starting_after' => $request->query('starting_after'),
                'ending_before' => $request->query('ending_before'),
            ];

            $result = $this->historyService->fetchHistory($repository, $filters);
            $result['repository'] = [
                'id' => $repository->id,
                'key' => $repository->key,
                'gateway' => $repository->payment_gateway?->name ?? 'Unknown Gateway',
                'gateway_key' => $repository->payment_gateway?->key ?? 'unknown',
                'mode' => $repository->mode?->value ?? (string) $repository->mode,
            ];

            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }
}
