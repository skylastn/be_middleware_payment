<?php

namespace App\Services\Payment;

use App\Enums\ProjectSlug;
use App\Interface\RedisServiceInterface;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentCategory;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Model\Entity\Setting;
use App\Repository\Payment\PaymentCategoryRepository;
use App\Repository\Payment\PaymentGatewayRepository;
use App\Repository\Payment\PaymentMethodRepository;
use App\Repository\Payment\PaymentRepositoryRepository;
use App\Repository\System\SettingRepository;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentService
{
    private PaymentCategoryRepository $paymentCategories;

    private PaymentMethodRepository $paymentMethods;

    private PaymentGatewayRepository $paymentGateways;

    private PaymentRepositoryRepository $paymentRepositories;

    private SettingRepository $settings;

    private ProjectService $projectService;

    private RedisServiceInterface $redisService;

    public function __construct(
        ?PaymentCategoryRepository $paymentCategories = null,
        ?PaymentMethodRepository $paymentMethods = null,
        ?PaymentGatewayRepository $paymentGateways = null,
        ?PaymentRepositoryRepository $paymentRepositories = null,
        ?SettingRepository $settings = null,
        ?ProjectService $projectService = null,
        ?RedisServiceInterface $redisService = null
    ) {
        $this->paymentCategories = $paymentCategories ?? new PaymentCategoryRepository;
        $this->paymentMethods = $paymentMethods ?? new PaymentMethodRepository;
        $this->paymentGateways = $paymentGateways ?? new PaymentGatewayRepository;
        $this->paymentRepositories = $paymentRepositories ?? new PaymentRepositoryRepository;
        $this->settings = $settings ?? new SettingRepository;
        $this->projectService = $projectService ?? new ProjectService;
        $this->redisService = $redisService ?? app(RedisServiceInterface::class);
    }

    public function getBankCodeMapByGatewayKey(string $gatewayKey): array
    {
        return $this->paymentMethods->getBankCodeMapByGatewayKey($gatewayKey);
    }

    public function getPaymentMethodByKeyAndGatewayKey(string $key, string $gatewayKey): ?PaymentMethod
    {
        return $this->paymentMethods->findByKeyAndGatewayKey($key, $gatewayKey);
    }

    public function invalidateCategoryCache(): void
    {
        try {
            $this->redisService->increment('payment:categories:version');
        } catch (\Throwable) {
            $this->redisService->set('payment:categories:version', time());
        }
    }

    public function invalidateMethodCache(): void
    {
        try {
            $this->redisService->increment('payment:methods:version');
        } catch (\Throwable) {
            $this->redisService->set('payment:methods:version', time());
        }
    }

    public function getListPaymentCategory(): Collection
    {
        $version = (int) ($this->redisService->get('payment:categories:version') ?: 1);

        return $this->redisService->remember("payment:categories:all:v{$version}", 86400, fn () => $this->paymentCategories->all());
    }

    public function getPaginatedPaymentCategory(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $search = $request->query('search');

        return $this->paymentCategories->latestPaginated($perPage, $search);
    }

    public function getPaymentCategoryById(int|string $id): ?PaymentCategory
    {
        return $this->paymentCategories->find($id);
    }

    public function createPaymentCategory(array $data): PaymentCategory
    {
        $category = $this->paymentCategories->create($data);
        $this->invalidateCategoryCache();
        $this->invalidateMethodCache();

        return $category;
    }

    public function updatePaymentCategory(int|string $id, array $data): PaymentCategory
    {
        $category = $this->paymentCategories->find($id);
        if (! $category) {
            throw new Exception('Payment Category Not Found', 404);
        }

        $updated = $this->paymentCategories->update($category, $data);
        $this->invalidateCategoryCache();
        $this->invalidateMethodCache();

        return $updated;
    }

    public function deletePaymentCategory(int|string $id): bool
    {
        $category = $this->paymentCategories->find($id);
        if (! $category) {
            throw new Exception('Payment Category Not Found', 404);
        }

        $deleted = $this->paymentCategories->delete($category);
        $this->invalidateCategoryCache();
        $this->invalidateMethodCache();

        return $deleted;
    }

    public function getListPaymentMethod(Request $request, ?bool $onlyActive = null): Collection
    {
        $gatewayId = $request->query('payment_gateway_id', $request->query('paymentGatewayId'));
        $gatewayKey = $request->query('payment_gateway_key', $request->query('paymentGatewayKey', $request->query('from')));
        $isActive = $onlyActive ?? ($request->has('is_active') ? $request->boolean('is_active') : null);
        $categoriesKey = $request->categoriesKey;

        $version = (int) ($this->redisService->get('payment:methods:version') ?: 1);
        $cacheKey = sprintf(
            'payment:methods:v%d:%s:%s:%s:%s',
            $version,
            is_array($categoriesKey) ? implode(',', $categoriesKey) : (string) $categoriesKey,
            (string) $gatewayId,
            $isActive === null ? 'all' : ($isActive ? '1' : '0'),
            (string) $gatewayKey
        );

        return $this->redisService->remember($cacheKey, 86400, function () use ($categoriesKey, $gatewayId, $isActive, $gatewayKey) {
            return $this->paymentMethods->filtered($categoriesKey, $gatewayId, $isActive, $gatewayKey);
        });
    }

    public function getPaginatedPaymentMethod(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $search = $request->query('search');
        $gatewayId = $request->query('payment_gateway_id', $request->query('paymentGatewayId'));
        $gatewayKey = $request->query('payment_gateway_key', $request->query('paymentGatewayKey', $request->query('from')));
        $categoriesKey = $request->query('categoriesKey');
        $isActive = $request->has('is_active') && $request->query('is_active') !== 'all' ? $request->boolean('is_active') : null;

        return $this->paymentMethods->latestPaginated($perPage, $search, $gatewayId, $categoriesKey, $isActive, $gatewayKey);
    }

    public function getDetailPaymentMethod(?string $key, ?string $paymentGatewayId = null, ?bool $onlyActive = null, ?string $paymentGatewayKey = null): ?PaymentMethod
    {
        $version = (int) ($this->redisService->get('payment:methods:version') ?: 1);
        $cacheKey = sprintf(
            'payment:method:detail:v%d:%s:%s:%s:%s',
            $version,
            (string) $key,
            (string) $paymentGatewayId,
            $onlyActive === null ? 'all' : ($onlyActive ? '1' : '0'),
            (string) $paymentGatewayKey
        );

        return $this->redisService->remember($cacheKey, 86400, function () use ($key, $paymentGatewayId, $onlyActive, $paymentGatewayKey) {
            return $this->paymentMethods->detail($key, $paymentGatewayId, $onlyActive, $paymentGatewayKey);
        });
    }

    public function getPaymentMethodById(int|string $id): ?PaymentMethod
    {
        return $this->paymentMethods->find($id);
    }

    public function processPaymentMethodImage(array $data): array
    {
        if (! empty($data['image']) && is_string($data['image'])) {
            if (str_starts_with($data['image'], 'data:image/')) {
                $imageParts = explode(';base64,', $data['image']);
                if (count($imageParts) === 2) {
                    $header = strtolower($imageParts[0]);
                    $imageType = 'png';
                    if (Str::contains($header, 'svg')) {
                        $imageType = 'svg';
                    } elseif (Str::contains($header, ['jpeg', 'jpg'])) {
                        $imageType = 'jpg';
                    } elseif (Str::contains($header, 'webp')) {
                        $imageType = 'webp';
                    }
                    $imageBase64 = base64_decode($imageParts[1]);
                    $fileName = 'payment-methods/'.uniqid('pm_').'.'.$imageType;
                    Storage::disk('public')->put($fileName, $imageBase64);
                    $data['image'] = $fileName;
                }
            } elseif (Str::contains($data['image'], '/storage/')) {
                $parts = explode('/storage/', $data['image']);
                $data['image'] = end($parts);
            }
        }

        return $data;
    }

    public function createPaymentMethod(array $data): PaymentMethod
    {
        $data = $this->processPaymentMethodImage($data);
        $data['bankCode'] = $data['bankCode'] ?? '';
        if (! array_key_exists('is_active', $data) || $data['is_active'] === null) {
            $data['is_active'] = true;
        }

        $method = $this->paymentMethods->create($data)->load(['category', 'payment_gateway']);
        $this->invalidateMethodCache();

        return $method;
    }

    public function updatePaymentMethod(int|string $id, array $data): PaymentMethod
    {
        $method = $this->paymentMethods->find($id);
        if (! $method) {
            throw new Exception('Payment Method Not Found', 404);
        }

        $data = $this->processPaymentMethodImage($data);
        if (array_key_exists('bankCode', $data) && $data['bankCode'] === null) {
            $data['bankCode'] = '';
        }
        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        $updated = $this->paymentMethods->update($method, $data)->load(['category', 'payment_gateway']);
        $this->invalidateMethodCache();

        return $updated;
    }

    public function deletePaymentMethod(int|string $id): bool
    {
        $method = $this->paymentMethods->find($id);
        if (! $method) {
            throw new Exception('Payment Method Not Found', 404);
        }

        $deleted = $this->paymentMethods->delete($method);
        $this->invalidateMethodCache();

        return $deleted;
    }

    public function togglePaymentMethod(int|string $id, ?bool $isActive = null): PaymentMethod
    {
        $method = $this->paymentMethods->find($id);
        if (! $method) {
            throw new Exception('Payment Method Not Found', 404);
        }

        $newStatus = $isActive !== null ? $isActive : ! $method->getIsActive();

        $updated = $this->paymentMethods->update($method, ['is_active' => $newStatus])->load(['category', 'payment_gateway']);
        $this->invalidateMethodCache();

        return $updated;
    }

    public function getPaginatedPaymentGateway(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $search = $request->query('search');

        return $this->paymentGateways->latestPaginated($perPage, $search);
    }

    public function getPaymentGatewayById(int|string $id): ?PaymentGateway
    {
        return $this->paymentGateways->find($id);
    }

    public function createPaymentGateway(array $data): PaymentGateway
    {
        return $this->paymentGateways->create($data);
    }

    public function updatePaymentGateway(int|string $id, array $data): PaymentGateway
    {
        $gateway = $this->paymentGateways->find($id);
        if (! $gateway) {
            throw new Exception('Payment Gateway Not Found', 404);
        }

        return $this->paymentGateways->update($gateway, $data);
    }

    public function deletePaymentGateway(int|string $id): bool
    {
        $gateway = $this->paymentGateways->find($id);
        if (! $gateway) {
            throw new Exception('Payment Gateway Not Found', 404);
        }

        return $this->paymentGateways->delete($gateway);
    }

    public function getPaginatedPaymentRepository(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $search = $request->query('search');
        $mode = $request->query('mode');

        return $this->paymentRepositories->latestPaginated($perPage, $search, $mode);
    }

    public function getPaymentRepositoryById(int|string $id): ?PaymentRepository
    {
        return $this->paymentRepositories->find($id);
    }

    public function createPaymentRepository(array $data): PaymentRepository
    {
        return $this->paymentRepositories->create($data);
    }

    public function updatePaymentRepository(int|string $id, array $data): PaymentRepository
    {
        $repository = $this->paymentRepositories->find($id);
        if (! $repository) {
            throw new Exception('Payment Repository Not Found', 404);
        }

        return $this->paymentRepositories->update($repository, $data);
    }

    public function deletePaymentRepository(int|string $id): bool
    {
        $repository = $this->paymentRepositories->find($id);
        if (! $repository) {
            throw new Exception('Payment Repository Not Found', 404);
        }

        return $this->paymentRepositories->delete($repository);
    }

    public function getPaginatedSetting(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $search = $request->query('search');

        return $this->settings->latestPaginated($perPage, $search);
    }

    public function getSettingById(int|string $id): ?Setting
    {
        return $this->settings->find($id);
    }

    public function createSetting(array $data): Setting
    {
        return $this->settings->create($data);
    }

    public function updateSetting(int|string $id, array $data): Setting
    {
        $setting = $this->settings->find($id);
        if (! $setting) {
            throw new Exception('Setting Not Found', 404);
        }

        return $this->settings->update($setting, $data);
    }

    public function deleteSetting(int|string $id): bool
    {
        $setting = $this->settings->find($id);
        if (! $setting) {
            throw new Exception('Setting Not Found', 404);
        }

        return $this->settings->delete($setting);
    }

    /**
     * Prepare test order request and project model for gateway execution.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function testCreateOrder(PaymentRepository $repository, array $params = []): array
    {
        $gatewayKey = strtolower(trim($repository->payment_gateway?->key ?? $repository->key ?? ''));
        $slug = ProjectSlug::tryFrom($gatewayKey);

        if (! $slug) {
            foreach (ProjectSlug::cases() as $case) {
                if (stripos($gatewayKey, $case->value) !== false) {
                    $slug = $case;
                    break;
                }
            }
        }

        if (! $slug) {
            throw new Exception("Unsupported payment gateway: {$gatewayKey}");
        }

        // Find or create test merchant project
        $project = $this->projectService->firstOrCreate(
            ['type' => 'TEST'],
            [
                'name' => 'System Test Project',
                'slug' => $slug->value,
                'key' => 'test_key',
                'secure' => 'test_secure',
                'callback' => env('APP_URL', 'http://localhost:8000').'/api/callback/'.$slug->value,
                'value' => 'test_project_token_system',
            ]
        );

        $project->slug = $slug;

        $amount = (float) ($params['paymentAmount'] ?? $params['amount'] ?? 10000);
        if ($amount <= 0) {
            throw new Exception('Test amount must be greater than 0');
        }
        $currency = $slug === ProjectSlug::STRIPE
            ? strtolower($params['currency'] ?? 'myr')
            : strtoupper($params['currency'] ?? 'IDR');
        $email = $params['email'] ?? 'test-buyer@example.com';
        $customerName = $params['name'] ?? 'Test Buyer';
        $paymentMethod = $params['paymentMethod'] ?? $params['payment_method'] ?? '';
        if ($slug === ProjectSlug::PAPRIKA && $paymentMethod === '') {
            $paymentMethod = 'qris';
        }
        $mode = $repository->mode?->value ?? (string) $repository->mode;
        $orderNumber = strtoupper($slug->value).'-'.time().rand(10, 99);
        $version = isset($params['version']) && $params['version'] !== '' ? (string) $params['version'] : '1';

        $requestData = [
            'paymentRepositoryId' => $repository->id,
            'merchantOrderId' => $orderNumber,
            'paymentAmount' => $amount,
            'paymentMethod' => $paymentMethod,
            'productDetails' => "Test Order for {$repository->payment_gateway?->name} ({$mode})",
            'email' => $email,
            'firstName' => $customerName,
            'lastName' => 'QA',
            'phone' => '08123456789',
            'address' => 'Jakarta, Indonesia',
            'customerVaName' => $customerName,
            'currency' => $currency,
            'mode' => $mode,
            'returnUrl' => $params['returnUrl'] ?? $params['return_url'] ?? (env('APP_URL', 'http://localhost:8000').'/admin/payment-repositories'),
            'callbackUrl' => env('APP_URL', 'http://localhost:8000').'/api/callback/'.$slug->value,
            'expiryPeriod' => 60,
            'version' => $version,
        ];

        $simulatedRequest = new Request($requestData);

        return [
            'request' => $simulatedRequest,
            'project' => $project,
            'slug' => $slug,
            'mode' => $mode,
            'order_number' => $orderNumber,
            'amount' => $amount,
            'currency' => $currency,
            'version' => $version,
            'gateway' => $repository->payment_gateway?->name ?? strtoupper($slug->value),
        ];
    }
}
