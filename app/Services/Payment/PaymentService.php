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

class PaymentService
{
    private SPNPayService $spnPayService;

    private StripeService $stripeService;

    private XenditService $xenditService;

    private MidtransService $midtransService;

    private DuitkuService $duitkuService;

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

    public function getListPaymentMethod(Request $request): Collection
    {
        return $this->paymentMethods->filtered($request->categoriesKey, $request->from);
    }

    public function getPaginatedPaymentMethod(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $search = $request->query('search');
        $from = $request->query('from');
        $categoriesKey = $request->query('categoriesKey');

        return $this->paymentMethods->latestPaginated($perPage, $search, $from, $categoriesKey);
    }

    public function getDetailPaymentMethod(?string $value, ?string $from): ?PaymentMethod
    {
        return $this->paymentMethods->detail($value, $from);
    }

    public function getPaymentMethodById(int|string $id): ?PaymentMethod
    {
        return $this->paymentMethods->find($id);
    }

    public function createPaymentMethod(array $data): PaymentMethod
    {
        return $this->paymentMethods->create($data);
    }

    public function updatePaymentMethod(int|string $id, array $data): PaymentMethod
    {
        $method = $this->paymentMethods->find($id);
        if (! $method) {
            throw new Exception('Payment Method Not Found', 404);
        }

        return $this->paymentMethods->update($method, $data);
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
            // case ProjectSlug::DUITKU:
            //     return $this->duitkuService->orderDuitku($request, $project);
            case ProjectSlug::SPNPAY:
                return $this->spnPayService->createOrderPaymentSPNPay($request, $project, $order);
            case ProjectSlug::STRIPE:
                return $this->stripeService->order($request, $project);
            default:
                throw new Exception('Undefined Project');
        }
    }
}
