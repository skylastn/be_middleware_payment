<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Model\Entity\Order;
use App\Model\Entity\Project;
use App\Model\Response\Order\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderGlobalValueTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::firstOrCreate(
            ['type' => 'GV'],
            [
                'name' => 'Global Value Test',
                'slug' => 'duitku',
                'key' => 'gvkey123',
                'secure' => 'gvsecure123',
                'value' => 'gv_token_' . Str::random(32),
                'callback' => 'https://example.com/callback',
            ]
        );
    }

    protected function tearDown(): void
    {
        Order::where('type', 'GV')->delete();
        parent::tearDown();
    }

    public function test_order_entity_persists_and_retrieves_value_field(): void
    {
        $order = Order::create([
            'id' => 'ORDER-GV-001',
            'type' => 'GV',
            'reference' => 'GV-TEST-001',
            'payment_method' => 'BC',
            'value' => '1234567890123456',
            'status' => OrderStatus::PENDING->value,
            'mode' => PaymentModeType::sandbox->value,
        ]);

        $this->assertEquals('1234567890123456', $order->getValue());

        $fresh = Order::find('ORDER-GV-001');
        $this->assertNotNull($fresh);
        $this->assertEquals('1234567890123456', $fresh->getValue());

        $fresh->setValue('00020101021226590014ID.LINKAJA.WWW01189360091100222718715204581253033605802ID5911Merchant5802ID');
        $fresh->save();

        $updated = Order::find('ORDER-GV-001');
        $this->assertStringStartsWith('000201', $updated->getValue());
    }

    public function test_order_resource_serializes_value_field(): void
    {
        $order = Order::create([
            'id' => 'ORDER-GV-002',
            'type' => 'GV',
            'reference' => 'GV-TEST-002',
            'payment_method' => 'QRIS',
            'value' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            'status' => OrderStatus::PENDING->value,
            'mode' => PaymentModeType::sandbox->value,
        ]);

        $resource = new OrderResource($order);
        $array = $resource->toArray(Request::create('/admin/orders/' . $order->id));

        $this->assertArrayHasKey('value', $array);
        $this->assertEquals('https://checkout.stripe.com/c/pay/cs_test_123', $array['value']);
    }

    public function test_order_entity_persists_and_serializes_return_url(): void
    {
        $order = Order::create([
            'id' => 'ORDER-GV-003',
            'type' => 'GV',
            'reference' => 'GV-TEST-003',
            'payment_method' => 'BC',
            'value' => '1234567890123456',
            'return_url' => 'https://merchant.example.com/checkout/success',
            'status' => OrderStatus::PENDING->value,
            'mode' => PaymentModeType::sandbox->value,
        ]);

        $this->assertEquals('https://merchant.example.com/checkout/success', $order->getReturnUrl());

        $fresh = Order::find('ORDER-GV-003');
        $this->assertNotNull($fresh);
        $this->assertEquals('https://merchant.example.com/checkout/success', $fresh->getReturnUrl());

        $resource = new OrderResource($fresh);
        $array = $resource->toArray(Request::create('/admin/orders/' . $fresh->id));

        $this->assertArrayHasKey('return_url', $array);
        $this->assertEquals('https://merchant.example.com/checkout/success', $array['return_url']);
    }

    public function test_order_entity_persists_and_serializes_amount_field(): void
    {
        $order = Order::create([
            'id' => 'ORDER-GV-004',
            'type' => 'GV',
            'reference' => 'GV-TEST-004',
            'payment_method' => 'BC',
            'amount' => 50000.50,
            'status' => OrderStatus::PENDING->value,
            'mode' => PaymentModeType::sandbox->value,
        ]);

        $this->assertEquals(50000.50, $order->getAmount());

        $fresh = Order::find('ORDER-GV-004');
        $this->assertNotNull($fresh);
        $this->assertEquals(50000.50, $fresh->getAmount());

        $resource = new OrderResource($fresh);
        $array = $resource->toArray(Request::create('/admin/orders/' . $fresh->id));

        $this->assertArrayHasKey('amount', $array);
        $this->assertEquals(50000.50, $array['amount']);
    }
}
