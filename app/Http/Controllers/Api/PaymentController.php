<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Entity\PaymentCategory;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Setting;
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
        $query = PaymentCategory::query();
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%");
            });
        }

        if (! $request->has('page') && ! $request->has('search') && ! $request->has('per_page') && ! $request->has('perPage') && ! auth('sanctum')->check()) {
            return ResponseHelper::successResponse($this->paymentService->getListPaymentCategory());
        }

        $perPage = (int) ($request->query('per_page', $request->query('perPage', 15)));
        return ResponseHelper::formatPagination($query->latest()->paginate($perPage));
    }

    public function getPaymentMethod(Request $request): JsonResponse
    {
        $query = PaymentMethod::query();
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('from', 'like', "%{$search}%")
                    ->orWhere('bankCode', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
        }
        if ($from = $request->query('from')) {
            $query->where('from', $from);
        }
        if ($categoriesKey = $request->query('categoriesKey')) {
            $keys = is_array($categoriesKey) ? $categoriesKey : [$categoriesKey];
            $query->whereIn('key', $keys);
        }

        if (! $request->has('page') && ! $request->has('search') && ! $request->has('per_page') && ! $request->has('perPage') && ! auth('sanctum')->check()) {
            $result = $this->paymentService->getListPaymentMethod($request);
            return ResponseHelper::successResponse($result);
        }

        $perPage = (int) ($request->query('per_page', $request->query('perPage', 15)));
        return ResponseHelper::formatPagination($query->latest()->paginate($perPage));
    }

    public function getDetailPaymentMethod(Request $request): JsonResponse
    {
        $value          = $request->value;
        $from           = $request->from;
        $result         = $this->paymentService->getDetailPaymentMethod($value, $from);
        return ResponseHelper::successResponse($result);
    }



    public function createPayment(Request $request): JsonResponse
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
        $query = PaymentGateway::query();
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 15)));
        return ResponseHelper::formatPagination($query->latest()->paginate($perPage));
    }

    public function getPaymentRepository(Request $request): JsonResponse
    {
        $query = PaymentRepository::query();
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('payment_gateway_id', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
        }
        if ($mode = $request->query('mode')) {
            if ($mode !== 'all') {
                $query->where('mode', $mode);
            }
        }
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 15)));
        return ResponseHelper::formatPagination($query->latest()->paginate($perPage));
    }

    public function getSetting(Request $request): JsonResponse
    {
        $query = Setting::query();
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
        }
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 15)));
        return ResponseHelper::formatPagination($query->latest()->paginate($perPage));
    }

    public function showPaymentCategory(int|string $id): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentCategory::query()->findOrFail($id));
    }

    public function createPaymentCategory(Request $request): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentCategory::query()->create($request->validate([
            'key' => ['required'],
            'title' => ['required'],
            'detail' => ['required'],
        ])), 'Success Create Payment Category', 201);
    }

    public function updatePaymentCategory(Request $request, int|string $id): JsonResponse
    {
        $category = PaymentCategory::query()->findOrFail($id);
        $category->update($request->validate([
            'key' => ['required'],
            'title' => ['required'],
            'detail' => ['required'],
        ]));

        return ResponseHelper::successResponse($category->refresh());
    }

    public function deletePaymentCategory(int|string $id): JsonResponse
    {
        PaymentCategory::query()->findOrFail($id)->delete();

        return ResponseHelper::successResponse(null, 'Success Delete Payment Category');
    }

    public function showPaymentMethod(int|string $id): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentMethod::query()->findOrFail($id));
    }

    public function createPaymentMethod(Request $request): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentMethod::query()->create($request->validate([
            'key' => ['required'],
            'name' => ['required'],
            'type' => ['required'],
            'from' => ['required'],
            'bankCode' => ['nullable'],
            'value' => ['nullable'],
        ])), 'Success Create Payment Method', 201);
    }

    public function updatePaymentMethod(Request $request, int|string $id): JsonResponse
    {
        $method = PaymentMethod::query()->findOrFail($id);
        $method->update($request->validate([
            'key' => ['required'],
            'name' => ['required'],
            'type' => ['required'],
            'from' => ['required'],
            'bankCode' => ['nullable'],
            'value' => ['nullable'],
        ]));

        return ResponseHelper::successResponse($method->refresh());
    }

    public function deletePaymentMethod(int|string $id): JsonResponse
    {
        PaymentMethod::query()->findOrFail($id)->delete();

        return ResponseHelper::successResponse(null, 'Success Delete Payment Method');
    }

    public function showPaymentGateway(int|string $id): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentGateway::query()->findOrFail($id));
    }

    public function createPaymentGateway(Request $request): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentGateway::query()->create($request->validate([
            'key' => ['required'],
            'name' => ['required'],
            'description' => ['required'],
        ])), 'Success Create Payment Gateway', 201);
    }

    public function updatePaymentGateway(Request $request, int|string $id): JsonResponse
    {
        $gateway = PaymentGateway::query()->findOrFail($id);
        $gateway->update($request->validate([
            'key' => ['required'],
            'name' => ['required'],
            'description' => ['required'],
        ]));

        return ResponseHelper::successResponse($gateway->refresh());
    }

    public function deletePaymentGateway(int|string $id): JsonResponse
    {
        PaymentGateway::query()->findOrFail($id)->delete();

        return ResponseHelper::successResponse(null, 'Success Delete Payment Gateway');
    }

    public function showPaymentRepository(int|string $id): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentRepository::query()->findOrFail($id));
    }

    public function createPaymentRepository(Request $request): JsonResponse
    {
        return ResponseHelper::successResponse(PaymentRepository::query()->create($this->paymentRepositoryPayload($request)), 'Success Create Payment Repository', 201);
    }

    public function updatePaymentRepository(Request $request, int|string $id): JsonResponse
    {
        $repository = PaymentRepository::query()->findOrFail($id);
        $repository->update($this->paymentRepositoryPayload($request));

        return ResponseHelper::successResponse($repository->refresh());
    }

    public function deletePaymentRepository(int|string $id): JsonResponse
    {
        PaymentRepository::query()->findOrFail($id)->delete();

        return ResponseHelper::successResponse(null, 'Success Delete Payment Repository');
    }

    public function showSetting(int|string $id): JsonResponse
    {
        return ResponseHelper::successResponse(Setting::query()->findOrFail($id));
    }

    public function createSetting(Request $request): JsonResponse
    {
        return ResponseHelper::successResponse(Setting::query()->create($request->validate([
            'key' => ['required'],
            'value' => ['required'],
        ])), 'Success Create Setting', 201);
    }

    public function updateSetting(Request $request, int|string $id): JsonResponse
    {
        $setting = Setting::query()->findOrFail($id);
        $setting->update($request->validate([
            'key' => ['required'],
            'value' => ['required'],
        ]));

        return ResponseHelper::successResponse($setting->refresh());
    }

    public function deleteSetting(int|string $id): JsonResponse
    {
        Setting::query()->findOrFail($id)->delete();

        return ResponseHelper::successResponse(null, 'Success Delete Setting');
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
