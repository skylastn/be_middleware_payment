<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Http\Helper\RequestHelper;
use App\Jobs\DeliverMerchantCallback;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Services\Payment\CallbackDeliveryService;
use App\Services\Payment\DuitkuService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckoutReliabilityTest extends TestCase
{
    private Project $project;
    private DuitkuService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            foreach (['name', 'type', 'key', 'secure', 'value', 'callback', 'slug'] as $field) $t->string($field)->nullable();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('reference')->unique();
            foreach (['type', 'mode', 'payment_method', 'name', 'return_url', 'payment_repository_id', 'value', 'status', 'url', 'request', 'response', 'callback'] as $field) $t->text($field)->nullable();
            $t->decimal('amount', 16, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('payment_methods', function (Blueprint $t) { $t->id(); $t->string('key'); });
        Schema::create('payment_repositories', function (Blueprint $t) { $t->string('id'); $t->softDeletes(); });
        (require database_path('migrations/2026_09_14_000001_add_checkout_delivery_state.php'))->up();
        (require database_path('migrations/2026_08_30_000001_create_order_histories_table.php'))->up();
        $this->project = Project::create(['name' => 'Test', 'type' => 'CHECKOUT', 'key' => 'project-key',
            'value' => 'test-token', 'callback' => 'https://merchant.test/callback', 'slug' => 'duitku']);
        $repo = new PaymentRepository(['mode' => 'sandbox', 'value' => ['duitku_mk' => 'test-secret', 'duitku_mc' => 'TEST']]);
        $repo->setAttribute('id', 'test-repository');
        $this->service = new class($repo) extends DuitkuService {
            public function __construct(private PaymentRepository $testRepository) { parent::__construct(); }
            public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository { return $this->testRepository; }
        };
        Redis::shouldReceive('incr')->andReturn(1);
        Redis::shouldReceive('expire')->andReturn(true);
        Bus::fake();
        Http::preventStrayRequests();
    }

    private function invoiceRequest(int $amount = 50000): Request
    {
        return new Request(['merchantOrderId' => 'checkout-1', 'paymentAmount' => $amount,
            'paymentRepositoryId' => 'test-repository', 'paymentMethod' => 'NQ', 'mode' => 'sandbox',
            'productDetails' => 'Order', 'expiryPeriod' => 60]);
    }

    private function existingOrder(): Order
    {
        return Order::without(['payment_methods', 'project', 'payment_repository'])->create([
            'id' => 'order-1', 'reference' => 'CHECKOUT-checkout-1', 'type' => 'CHECKOUT',
            'payment_repository_id' => 'test-repository', 'mode' => 'sandbox', 'status' => 'PENDING',
            'payment_method' => 'NQ', 'amount' => 50000,
        ]);
    }

    private function callbackRequest(): Request
    {
        return new Request(['merchantOrderId' => 'CHECKOUT-checkout-1', 'merchantCode' => 'TEST',
            'amount' => '50000', 'resultCode' => '00', 'paymentCode' => 'NQ',
            'signature' => md5('TEST'.'50000'.'CHECKOUT-checkout-1'.'test-secret')]);
    }

    public function test_invoice_retry_returns_the_same_qr_without_another_provider_call(): void
    {
        Http::fake(function () {
            $this->assertSame(0, DB::transactionLevel());
            return Http::response(['statusCode' => '00', 'paymentUrl' => 'https://duitku.test/pay', 'qrString' => 'qr-content']);
        });
        $first = $this->service->orderDuitku($this->invoiceRequest(), $this->project);
        $retry = $this->service->orderDuitku($this->invoiceRequest(), $this->project);
        $this->assertSame($first['result']->qrString, $retry['result']->qrString);
        $this->assertSame(1, Order::count());
        Http::assertSentCount(1);
    }

    public function test_timeout_keeps_a_durable_order_and_does_not_repeat_invoice_creation(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $first = $this->service->orderDuitku($this->invoiceRequest(), $this->project);
        $retry = $this->service->orderDuitku($this->invoiceRequest(), $this->project);
        $this->assertTrue($first['pending']);
        $this->assertTrue($retry['pending']);
        $this->assertSame('UNKNOWN', DB::table('orders')->value('invoice_state'));
        Http::assertSentCount(1);
    }

    public function test_reusing_reference_with_a_different_amount_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['statusCode' => '00', 'paymentUrl' => 'https://duitku.test/pay', 'qrString' => 'qr'])]);
        $this->service->orderDuitku($this->invoiceRequest(), $this->project);
        $this->expectExceptionMessage('different payment parameters');
        $this->service->orderDuitku($this->invoiceRequest(60000), $this->project);
    }

    public function test_duplicate_callback_changes_status_and_enqueues_delivery_once(): void
    {
        $this->existingOrder();
        $this->service->callback($this->callbackRequest());
        $this->service->callback($this->callbackRequest());
        $this->assertSame('SUCCESS', DB::table('orders')->value('status'));
        $this->assertSame(1, DB::table('order_histories')->count());
        $this->assertSame(1, DB::table('merchant_callback_deliveries')->count());
        Bus::assertDispatchedTimes(DeliverMerchantCallback::class, 1);
    }

    public function test_invalid_signature_is_rejected_even_for_an_already_paid_order(): void
    {
        $this->existingOrder();
        $this->service->callback($this->callbackRequest());
        $request = $this->callbackRequest();
        $request->merge(['signature' => 'invalid']);
        $this->expectExceptionMessage('Invalid Duitku callback');
        $this->service->callback($request);
    }

    public function test_hmac_callback_signature_is_accepted(): void
    {
        $this->existingOrder();
        $request = $this->callbackRequest();
        $request->merge(['signature' => hash_hmac('sha256', 'TEST'.'50000'.'CHECKOUT-checkout-1', 'test-secret')]);
        $this->service->callback($request);
        $this->assertSame('SUCCESS', DB::table('orders')->value('status'));
    }

    public function test_callback_delivery_is_not_dispatched_before_commit_and_rolls_back_with_status(): void
    {
        DB::beginTransaction();
        (new CallbackDeliveryService)->enqueue($this->project, 'checkout-1', ['merchantOrderId' => 'checkout-1']);
        Bus::assertNotDispatched(DeliverMerchantCallback::class);
        DB::rollBack();
        $this->assertSame(0, DB::table('merchant_callback_deliveries')->count());
    }

    public function test_failed_delivery_is_retryable_and_successful_delivery_is_not_repeated(): void
    {
        DB::transaction(fn () => (new CallbackDeliveryService)->enqueue($this->project, 'checkout-1', ['merchantOrderId' => 'checkout-1']));
        $id = DB::table('merchant_callback_deliveries')->value('id');
        Http::fake(['*' => Http::sequence()->push([], 503)->push(['message' => 'Success'])]);
        $service = new CallbackDeliveryService;
        try {
            $service->deliver($id);
            $this->fail('Delivery must fail so the queue can retry');
        } catch (RequestException $e) {
            $this->assertNull(DB::table('merchant_callback_deliveries')->value('delivered_at'));
            $this->assertSame(1, DB::table('merchant_callback_deliveries')->value('attempts'));
        }
        $service->deliver($id);
        $service->deliver($id);
        $this->assertNotNull(DB::table('merchant_callback_deliveries')->value('delivered_at'));
        Http::assertSentCount(2);
    }

    public function test_create_endpoint_returns_http_202_when_provider_confirmation_is_unknown(): void
    {
        $controller = new \App\Http\Controllers\Api\OrderController;
        $property = new \ReflectionProperty($controller, 'duitkuService');
        $property->setValue($controller, $this->service);
        $this->app->instance(\App\Http\Controllers\Api\OrderController::class, $controller);
        Http::fake(['*' => Http::failedConnection()]);
        $response = $this->withHeaders(['Token' => 'test-token'])->postJson('/api/order/create', $this->invoiceRequest()->all());
        $response->assertStatus(202)->assertJsonPath('data.pending', true);
    }

    public function test_provider_signature_and_endpoint_follow_the_current_contract(): void
    {
        Http::fake(['*' => Http::response(['statusCode' => '00', 'paymentUrl' => 'https://duitku.test/pay', 'qrString' => 'qr'])]);
        $this->service->orderDuitku($this->invoiceRequest(), $this->project);
        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry'
            && $request['signature'] === hash_hmac('sha256', 'TESTCHECKOUT-checkout-150000', 'test-secret'));
    }

    public function test_status_inquiry_recovers_a_missing_provider_callback(): void
    {
        $order = $this->existingOrder();
        Http::fake(['*transactionStatus' => Http::response(['statusCode' => '00', 'amount' => 50000])]);
        $this->service->reconcile($order->getId());
        $this->service->reconcile($order->getId());
        $this->assertSame('SUCCESS', DB::table('orders')->value('status'));
        $this->assertSame(1, DB::table('merchant_callback_deliveries')->count());
        Http::assertSentCount(1);
    }
}
