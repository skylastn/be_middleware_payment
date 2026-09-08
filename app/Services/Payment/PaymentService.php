<?php

namespace App\Services\Payment;

use App\Enums\ProjectSlug;
use App\Http\Helper\FormatHelper;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentCategory;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Model\Entity\Setting;
use App\Repository\Payment\OrderRepository;
use App\Repository\Payment\PaymentCategoryRepository;
use App\Repository\Payment\PaymentGatewayRepository;
use App\Repository\Payment\PaymentMethodRepository;
use App\Repository\Payment\PaymentRepositoryRepository;
use App\Repository\System\ProjectRepository;
use App\Repository\System\SettingRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    private SPNPayService $spnPayService;

    private StripeService $stripeService;

    private XenditService $xenditService;

    private MidtransService $midtransService;

    private DuitkuService $duitkuService;

    private PaprikaService $paprikaService;

    private OrderRepository $orders;

    private PaymentCategoryRepository $paymentCategories;

    private PaymentMethodRepository $paymentMethods;

    private PaymentGatewayRepository $paymentGateways;

    private PaymentRepositoryRepository $paymentRepositories;

    private SettingRepository $settings;

    private ProjectRepository $projects;

    public function __construct()
    {
        $this->spnPayService = new SPNPayService;
        $this->stripeService = new StripeService;
        $this->xenditService = new XenditService;
        $this->midtransService = new MidtransService;
        $this->duitkuService = new DuitkuService;
        $this->paprikaService = new PaprikaService;
        $this->orders = new OrderRepository;
        $this->paymentCategories = new PaymentCategoryRepository;
        $this->paymentMethods = new PaymentMethodRepository;
        $this->paymentGateways = new PaymentGatewayRepository;
        $this->paymentRepositories = new PaymentRepositoryRepository;
        $this->settings = new SettingRepository;
        $this->projects = new ProjectRepository;
    }

    public function getListPaymentCategory(): Collection
    {
        return $this->paymentCategories->all();
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
        return $this->paymentCategories->create($data);
    }

    public function updatePaymentCategory(int|string $id, array $data): PaymentCategory
    {
        $category = $this->paymentCategories->find($id);
        if (! $category) {
            throw new Exception('Payment Category Not Found', 404);
        }

        return $this->paymentCategories->update($category, $data);
    }

    public function deletePaymentCategory(int|string $id): bool
    {
        $category = $this->paymentCategories->find($id);
        if (! $category) {
            throw new Exception('Payment Category Not Found', 404);
        }

        return $this->paymentCategories->delete($category);
    }

    public function getListPaymentMethod(Request $request, ?bool $onlyActive = null): Collection
    {
        $gatewayId = $request->query('payment_gateway_id', $request->query('paymentGatewayId'));
        $gatewayKey = $request->query('payment_gateway_key', $request->query('paymentGatewayKey', $request->query('from')));
        $isActive = $onlyActive ?? ($request->has('is_active') ? $request->boolean('is_active') : null);

        return $this->paymentMethods->filtered($request->categoriesKey, $gatewayId, $isActive, $gatewayKey);
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
        return $this->paymentMethods->detail($key, $paymentGatewayId, $onlyActive, $paymentGatewayKey);
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
                    if (\Illuminate\Support\Str::contains($header, 'svg')) {
                        $imageType = 'svg';
                    } elseif (\Illuminate\Support\Str::contains($header, ['jpeg', 'jpg'])) {
                        $imageType = 'jpg';
                    } elseif (\Illuminate\Support\Str::contains($header, 'webp')) {
                        $imageType = 'webp';
                    }
                    $imageBase64 = base64_decode($imageParts[1]);
                    $fileName = 'payment-methods/' . uniqid('pm_') . '.' . $imageType;
                    Storage::disk('public')->put($fileName, $imageBase64);
                    $data['image'] = $fileName;
                }
            } elseif (\Illuminate\Support\Str::contains($data['image'], '/storage/')) {
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

        return $this->paymentMethods->create($data)->load(['category', 'payment_gateway']);
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

        return $this->paymentMethods->update($method, $data)->load(['category', 'payment_gateway']);
    }

    public function deletePaymentMethod(int|string $id): bool
    {
        $method = $this->paymentMethods->find($id);
        if (! $method) {
            throw new Exception('Payment Method Not Found', 404);
        }

        return $this->paymentMethods->delete($method);
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

    public function createPayment(Request $request, Project $project): ?array
    {
        $order = $this->orders->latestByReference($request->reference, $project->type);
        if (! FormatHelper::isNotEmpty($order)) {
            throw new Exception('Order Not Found', 404);
        }

        return $this->processOrderPayment($request, $project, $order);
    }

    public function processOrderPayment(Request $request, Project $project, Order $order): ?array
    {
        $slug = $project->getSlug();

        switch ($slug) {
            // case ProjectSlug::XENDIT:
            //     return $this->xenditService->order($request, $project);
            // case ProjectSlug::MIDTRANS:
            //     return $this->midtransService->orderMidtrans($request, $project);
            case ProjectSlug::DUITKU:
                return $this->duitkuService->createOrderPaymentDuitku($request, $project, $order);
            case ProjectSlug::SPNPAY:
                return $this->spnPayService->createOrderPaymentSPNPay($request, $project, $order);
            case ProjectSlug::STRIPE:
                return $this->stripeService->order($request, $project);
            case ProjectSlug::PAPRIKA:
                return $this->paprikaService->orderPaprika($request, $project);
            default:
                throw new Exception('Undefined Project');
        }
    }

    /**
     * Test create order directly against the configured payment repository.
     *
     * @param PaymentRepository $repository
     * @param array<string, mixed> $params
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
        $project = Project::firstOrCreate(
            ['type' => 'TEST'],
            [
                'name' => 'System Test Project',
                'slug' => $slug->value,
                'key' => 'test_key',
                'secure' => 'test_secure',
                'callback' => env('APP_URL', 'http://localhost:8000') . '/api/callback/test',
                'value' => 'test_project_token_system',
            ]
        );

        $project->slug = $slug;

        $amount = (float) ($params['amount'] ?? $params['paymentAmount'] ?? 10000);
        if ($amount <= 0) {
            throw new Exception('Test amount must be greater than 0');
        }
        $currency = strtolower($params['currency'] ?? ($slug === ProjectSlug::STRIPE ? 'myr' : 'idr'));
        $email = $params['email'] ?? 'test-buyer@example.com';
        $customerName = $params['name'] ?? 'Test Buyer';
        $paymentMethod = $params['paymentMethod'] ?? $params['payment_method'] ?? '';
        if ($slug === ProjectSlug::PAPRIKA && $paymentMethod === '') {
            $paymentMethod = 'qris';
        }
        $mode = $repository->mode?->value ?? (string) $repository->mode;
        $orderNumber = 'TEST-' . strtoupper($slug->value) . '-' . time() . rand(100, 999);
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
            'returnUrl' => env('APP_URL', 'http://localhost:8000') . '/admin/payment-repositories',
            'callbackUrl' => env('APP_URL', 'http://localhost:8000') . '/api/callback/' . $slug->value,
            'expiryPeriod' => 60,
            'version' => $version,
        ];

        $simulatedRequest = new Request($requestData);

        $result = match ($slug) {
            ProjectSlug::DUITKU => $this->duitkuService->orderDuitku($simulatedRequest, $project),
            ProjectSlug::MIDTRANS => $this->midtransService->orderMidtrans($simulatedRequest, $project),
            ProjectSlug::XENDIT => $this->xenditService->order($simulatedRequest, $project),
            ProjectSlug::SPNPAY => $this->spnPayService->createOrderSPNPay($simulatedRequest, $project),
            ProjectSlug::STRIPE => $this->stripeService->order($simulatedRequest, $project),
            ProjectSlug::PAPRIKA => $this->paprikaService->orderPaprika($simulatedRequest, $project),
        };

        return [
            'success' => true,
            'gateway' => $repository->payment_gateway?->name ?? strtoupper($slug->value),
            'mode' => $mode,
            'repository_id' => $repository->id,
            'order_reference' => 'TEST-' . $orderNumber,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'version' => $version,
            'checkout_url' => $result['link'] ?? $result['url'] ?? $result['invoice_url'] ?? $result['paymentUrl'] ?? null,
            'raw_result' => $result,
        ];
    }
}
