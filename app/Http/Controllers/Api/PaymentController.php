<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Request\Payment\CreatePaymentRequest;
use App\Model\Request\Payment\PaymentCategory\CreatePaymentCategoryRequest;
use App\Model\Request\Payment\PaymentGateway\CreatePaymentGatewayRequest;
use App\Model\Request\Payment\PaymentMethod\CreatePaymentMethodRequest;
use App\Model\Request\Payment\PaymentRepository\CreatePaymentRepositoryRequest;
use App\Model\Request\Payment\Setting\CreateSettingRequest;
use App\Model\Response\Payment\PaymentMethod\PaymentMethodResource;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    public function getPaymentCategory(Request $request): JsonResponse
    {
        if (! $request->has('page') && ! $request->has('search') && ! $request->has('per_page') && ! $request->has('perPage') && ! auth('sanctum')->check()) {
            return ResponseHelper::successResponse($this->paymentService->getListPaymentCategory());
        }

        return ResponseHelper::formatPagination($this->paymentService->getPaginatedPaymentCategory($request));
    }

    public function getPaymentMethod(Request $request): JsonResponse
    {
        if (! $request->has('page') && ! $request->has('search') && ! $request->has('per_page') && ! $request->has('perPage') && ! auth('sanctum')->check()) {
            return ResponseHelper::successResponse($this->paymentService->getListPaymentMethod($request));
        }

        return ResponseHelper::formatPagination($this->paymentService->getPaginatedPaymentMethod($request));
    }

    public function getDetailPaymentMethod(Request $request): JsonResponse
    {
        $key = $request->query('key', $request->query('value'));
        $from = $request->query('from');
        $result = $this->paymentService->getDetailPaymentMethod($key, $from);

        return ResponseHelper::successResponse($result ? new PaymentMethodResource($result) : null);
    }

    public function createPayment(CreatePaymentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $project = $request->attributes->get('project');
            $result = $this->paymentService->createPayment($request, $project);
            DB::commit();

            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            DB::rollBack();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function getPaymentGateway(Request $request): JsonResponse
    {
        return ResponseHelper::formatPagination($this->paymentService->getPaginatedPaymentGateway($request));
    }

    public function getPaymentRepository(Request $request): JsonResponse
    {
        return ResponseHelper::formatPagination($this->paymentService->getPaginatedPaymentRepository($request));
    }

    public function getSetting(Request $request): JsonResponse
    {
        return ResponseHelper::formatPagination($this->paymentService->getPaginatedSetting($request));
    }

    public function showPaymentCategory(int|string $id): JsonResponse
    {
        $category = $this->paymentService->getPaymentCategoryById($id);
        if (! $category) {
            return ResponseHelper::failedResponse('Payment Category Not Found', 'Not Found', 404);
        }

        return ResponseHelper::successResponse($category);
    }

    public function createPaymentCategory(CreatePaymentCategoryRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $category = $this->paymentService->createPaymentCategory($request->validated());
            DB::commit();

            return ResponseHelper::successResponse($category, 'Success Create Payment Category', 201);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function updatePaymentCategory(CreatePaymentCategoryRequest $request, int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $category = $this->paymentService->updatePaymentCategory($id, $request->validated());
            DB::commit();

            return ResponseHelper::successResponse($category);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function deletePaymentCategory(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->paymentService->deletePaymentCategory($id);
            DB::commit();

            return ResponseHelper::successResponse(null, 'Success Delete Payment Category');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function showPaymentMethod(int|string $id): JsonResponse
    {
        $method = $this->paymentService->getPaymentMethodById($id);
        if (! $method) {
            return ResponseHelper::failedResponse('Payment Method Not Found', 'Not Found', 404);
        }

        return ResponseHelper::successResponse(new PaymentMethodResource($method));
    }

    public function createPaymentMethod(CreatePaymentMethodRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $method = $this->paymentService->createPaymentMethod($request->validated());
            DB::commit();

            return ResponseHelper::successResponse(new PaymentMethodResource($method), 'Success Create Payment Method', 201);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function updatePaymentMethod(CreatePaymentMethodRequest $request, int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $method = $this->paymentService->updatePaymentMethod($id, $request->validated());
            DB::commit();

            return ResponseHelper::successResponse(new PaymentMethodResource($method));
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function deletePaymentMethod(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->paymentService->deletePaymentMethod($id);
            DB::commit();

            return ResponseHelper::successResponse(null, 'Success Delete Payment Method');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function showPaymentGateway(int|string $id): JsonResponse
    {
        $gateway = $this->paymentService->getPaymentGatewayById($id);
        if (! $gateway) {
            return ResponseHelper::failedResponse('Payment Gateway Not Found', 'Not Found', 404);
        }

        return ResponseHelper::successResponse($gateway);
    }

    public function createPaymentGateway(CreatePaymentGatewayRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $gateway = $this->paymentService->createPaymentGateway($request->validated());
            DB::commit();

            return ResponseHelper::successResponse($gateway, 'Success Create Payment Gateway', 201);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function updatePaymentGateway(CreatePaymentGatewayRequest $request, int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $gateway = $this->paymentService->updatePaymentGateway($id, $request->validated());
            DB::commit();

            return ResponseHelper::successResponse($gateway);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function deletePaymentGateway(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->paymentService->deletePaymentGateway($id);
            DB::commit();

            return ResponseHelper::successResponse(null, 'Success Delete Payment Gateway');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function showPaymentRepository(int|string $id): JsonResponse
    {
        $repository = $this->paymentService->getPaymentRepositoryById($id);
        if (! $repository) {
            return ResponseHelper::failedResponse('Payment Repository Not Found', 'Not Found', 404);
        }

        return ResponseHelper::successResponse($repository);
    }

    public function testOrder(Request $request, int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $repository = $this->paymentService->getPaymentRepositoryById($id);
            if (! $repository) {
                return ResponseHelper::failedResponse('Payment Repository Not Found', 'Not Found', 404);
            }

            $result = $this->paymentService->testCreateOrder($repository, $request->all());
            DB::commit();

            return ResponseHelper::successResponse($result, 'Test order created successfully on gateway.');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function createPaymentRepository(CreatePaymentRepositoryRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $repository = $this->paymentService->createPaymentRepository($this->paymentRepositoryPayload($request));
            DB::commit();

            return ResponseHelper::successResponse($repository, 'Success Create Payment Repository', 201);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function updatePaymentRepository(CreatePaymentRepositoryRequest $request, int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $repository = $this->paymentService->updatePaymentRepository($id, $this->paymentRepositoryPayload($request));
            DB::commit();

            return ResponseHelper::successResponse($repository);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function deletePaymentRepository(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->paymentService->deletePaymentRepository($id);
            DB::commit();

            return ResponseHelper::successResponse(null, 'Success Delete Payment Repository');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function showSetting(int|string $id): JsonResponse
    {
        $setting = $this->paymentService->getSettingById($id);
        if (! $setting) {
            return ResponseHelper::failedResponse('Setting Not Found', 'Not Found', 404);
        }

        return ResponseHelper::successResponse($setting);
    }

    public function createSetting(CreateSettingRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $setting = $this->paymentService->createSetting($request->validated());
            DB::commit();

            return ResponseHelper::successResponse($setting, 'Success Create Setting', 201);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function updateSetting(CreateSettingRequest $request, int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $setting = $this->paymentService->updateSetting($id, $request->validated());
            DB::commit();

            return ResponseHelper::successResponse($setting);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    public function deleteSetting(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->paymentService->deleteSetting($id);
            DB::commit();

            return ResponseHelper::successResponse(null, 'Success Delete Setting');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400);
        }
    }

    /**
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function paymentRepositoryPayload(Request $request): array
    {
        $data = $request->validate([
            'payment_gateway_id' => ['required'],
            'key' => ['nullable'],
            'mode' => ['required'],
            'value' => ['required'],
        ]);

        if (is_string($data['value'])) {
            $decoded = json_decode($data['value'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw ValidationException::withMessages([
                    'value' => ['The value field must be a valid JSON string or object.'],
                ]);
            }
            $data['value'] = $decoded;
        } elseif (!is_array($data['value'])) {
            throw ValidationException::withMessages([
                'value' => ['The value field must be a valid JSON object or array.'],
            ]);
        }

        return $data;
    }
}
