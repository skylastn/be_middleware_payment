<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectSlug;
use App\Http\Controllers\Controller;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Http\Helper\ResponseHelper;
use App\Interface\RedisServiceInterface;
use App\Services\Payment\DuitkuService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\OrderService;
use App\Services\Payment\PaprikaService;
use App\Services\Payment\SPNPayService;
use App\Services\Payment\StripeService;
use App\Services\Payment\XenditService;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    private OrderService $service;

    private ProjectService $projectService;

    private DuitkuService $duitkuService;

    private MidtransService $midtransService;

    private XenditService $xenditService;

    private SPNPayService $spnPayService;

    private StripeService $stripeService;

    private PaprikaService $paprikaService;

    private RedisServiceInterface $redisService;

    public function __construct(
        ?OrderService $service = null,
        ?ProjectService $projectService = null,
        ?DuitkuService $duitkuService = null,
        ?MidtransService $midtransService = null,
        ?XenditService $xenditService = null,
        ?SPNPayService $spnPayService = null,
        ?StripeService $stripeService = null,
        ?PaprikaService $paprikaService = null,
        ?RedisServiceInterface $redisService = null
    ) {
        $this->service = $service ?? new OrderService;
        $this->projectService = $projectService ?? new ProjectService;
        $this->duitkuService = $duitkuService ?? new DuitkuService;
        $this->midtransService = $midtransService ?? new MidtransService;
        $this->xenditService = $xenditService ?? new XenditService;
        $this->spnPayService = $spnPayService ?? new SPNPayService;
        $this->stripeService = $stripeService ?? new StripeService;
        $this->paprikaService = $paprikaService ?? new PaprikaService;
        $this->redisService = $redisService ?? app(RedisServiceInterface::class);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return ResponseHelper::formatPagination($this->service->getListOrder($request));
        } catch (Exception $ex) {
            $error['line'] = $ex->getLine();
            $error['message'] = $ex->getMessage();
            $error['file'] = $ex->getFile();
            Log::error($error);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function detail(Request $request): JsonResponse
    {
        try {
            $project = $this->projectService->checkKey();
            $response = $this->service->detailByReferenceAndKey($request->reference, $project->type);
            if (! FormatHelper::isNotEmpty($response)) {
                throw new Exception('Unknown Order', 400);
            }

            return ResponseHelper::successResponse($response);
        } catch (Exception $ex) {
            $error['line'] = $ex->getLine();
            $error['message'] = $ex->getMessage();
            $error['file'] = $ex->getFile();
            Log::error($error);

            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function show(int|string $id): JsonResponse
    {
        $order = $this->service->getOrderById($id);
        if (! $order) {
            return ResponseHelper::failedResponse('Order Not Found', 'Order Not Found', 404);
        }

        return ResponseHelper::successResponse($order);
    }

    public function checkOrderStatus(Request $request): JsonResponse
    {
        try {
            $project = $this->projectService->checkKey();
            $order = $this->service->detailByReferenceAndKey($request->reference, $project->type);
            if (! FormatHelper::isNotEmpty($order)) {
                throw new Exception('Order Not Found');
            }

            $result = match ($project->getSlug()) {
                ProjectSlug::DUITKU => $this->duitkuService->checkStatus($order),
                ProjectSlug::STRIPE => $this->stripeService->checkStatus($order),
                default => throw new Exception('Undefined Project'),
            };

            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function store(Request $request): JsonResponse
    {
        $lockKey = null;
        try {
            $project = $this->projectService->checkKey();
            $reference = $request->input('reference')
                ?? $request->input('merchantOrderId')
                ?? $request->input('order_id');

            if ($reference) {
                $lockKey = "lock:order:create:{$project->type}:{$reference}";
                if (! $this->redisService->lock($lockKey, 5)) {
                    return ResponseHelper::failedResponse(
                        'Duplicate order request detected. Please wait a few seconds before retrying.',
                        'Duplicate Order Request',
                        409
                    );
                }
            }

            DB::beginTransaction();
            $response = match ($project->getSlug()) {
                ProjectSlug::XENDIT => $this->xenditService->order($request, $project),
                ProjectSlug::MIDTRANS => $this->midtransService->orderMidtrans($request, $project),
                ProjectSlug::DUITKU => $this->duitkuService->orderDuitku($request, $project),
                ProjectSlug::SPNPAY => $this->spnPayService->createOrderSPNPay($request, $project),
                ProjectSlug::STRIPE => $this->stripeService->order($request, $project),
                ProjectSlug::PAPRIKA => $this->paprikaService->orderPaprika($request, $project),
                default => throw new Exception('Undefined Project'),
            };
            DB::commit();

            return ResponseHelper::successResponse($response);
        } catch (Exception $ex) {
            if ($lockKey) {
                $this->redisService->unlock($lockKey);
            }
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);
            if ($ex->getMessage() === 'Unauthorized') {
                return ResponseHelper::unauthorizedResponse($ex->getMessage());
            }

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function confirmStripe(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $project = $this->projectService->checkKey();
            if ($project->getSlug() != ProjectSlug::STRIPE) {
                throw new Exception('Stripe confirm only supported for Stripe projects');
            }
            $response = $this->stripeService->confirmCardPayment($request, $project);
            DB::commit();

            return ResponseHelper::successResponse($response);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function setSuccessMerchant(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $order = $this->service->setSuccessMerchant($request);

            DB::commit();

            return ResponseHelper::successResponse($order);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }

            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function setSuccessAdmin(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $updatedOrder = $this->service->setSuccessById($id);
            DB::commit();

            return ResponseHelper::successResponse($updatedOrder, 'Order status updated to SUCCESS and callback sent.');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            $code = $ex->getCode() >= 400 && $ex->getCode() < 600 ? $ex->getCode() : 400;

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), $code, $ex->getLine(), $ex->getFile());
        }
    }

    public function destroy(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->service->deleteOrder($id);
            DB::commit();

            return ResponseHelper::successResponse(null, 'Success Delete Order');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            $code = $ex->getCode() >= 400 && $ex->getCode() < 600 ? $ex->getCode() : 400;

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), $code);
        }
    }

    public function resendCallback(int|string $id): JsonResponse
    {
        try {
            /** @var Order $order */
            $order = $this->service->getOrderById($id);
            if (! $order) {
                return ResponseHelper::failedResponse('Order Not Found', 'Order Not Found', 404);
            }
            $status = $order->getStatus();
            if (! $status?->isSuccess()) {
                throw new Exception('Only successful orders can resend callback.');
            }

            $project = $order->project ?: $this->projectService->getByType($order->type);
            if (! $project || ! $project->value || ! $project->callback) {
                throw new Exception('Project callback configuration is incomplete.');
            }

            $referenceParts = explode('-', (string) $order->reference);
            array_shift($referenceParts);

            RequestHelper::sendCallback(
                $project->value,
                [
                    'merchantOrderId' => implode('-', $referenceParts),
                    'paymentCode' => $order->payment_method,
                    'resultCode' => '00',
                ],
                $project->callback,
            );

            return ResponseHelper::successResponse(null, 'Callback resent for '.$order->reference.'.');
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            $code = $ex->getCode() >= 400 && $ex->getCode() < 600 ? $ex->getCode() : 400;

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), $code);
        }
    }
}
