<?php

namespace Tests\Unit;

use App\Enums\SurchargeMode;
use App\Model\Entity\PaymentRepository;
use App\Services\Payment\StripeService;
use Illuminate\Http\Request;
use Tests\TestCase;

class StripeSurchargeTest extends TestCase
{
    private StripeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StripeService();
    }

    public function test_surcharge_mode_enum_helpers(): void
    {
        $this->assertEquals(['none', 'middleware_calc', 'stripe_automatic'], SurchargeMode::values());

        $this->assertEquals(SurchargeMode::MIDDLEWARE_CALC, SurchargeMode::fromName('middleware_calc'));
        $this->assertEquals(SurchargeMode::MIDDLEWARE_CALC, SurchargeMode::fromName('calc'));
        $this->assertEquals(SurchargeMode::MIDDLEWARE_CALC, SurchargeMode::fromName('gross_up'));

        $this->assertEquals(SurchargeMode::STRIPE_AUTOMATIC, SurchargeMode::fromName('stripe_automatic'));
        $this->assertEquals(SurchargeMode::STRIPE_AUTOMATIC, SurchargeMode::fromName('automatic'));
        $this->assertEquals(SurchargeMode::STRIPE_AUTOMATIC, SurchargeMode::fromName('stripe'));

        $this->assertEquals(SurchargeMode::NONE, SurchargeMode::fromName('none'));
        $this->assertNull(SurchargeMode::fromName('invalid_mode'));
        $this->assertNull(SurchargeMode::fromName(null));

        $this->assertTrue(SurchargeMode::MIDDLEWARE_CALC->isMiddlewareCalc());
        $this->assertTrue(SurchargeMode::STRIPE_AUTOMATIC->isStripeAutomatic());
        $this->assertTrue(SurchargeMode::NONE->isNone());
    }

    public function test_default_middleware_calc_with_currency_rates(): void
    {
        $repo = new PaymentRepository();
        $repo->value = [];

        // When request is plain without surcharge fields, defaults to middleware_calc with MYR rates (3.0% + RM 1.00)
        $request = new Request([
            'paymentAmount' => 150,
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveSurcharge');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $request, $repo, 150, 'myr');

        $this->assertEquals('middleware_calc', $result['mode']);
        // Gross raw: ceil((150 + 1.00) / (1 - 0.03)) = ceil(151 / 0.97) = ceil(155.67) = 156
        // Fee raw = 156 - 150 = 6
        $this->assertEquals(6, $result['fee_amount_raw']);
        $this->assertEquals(600, $result['fee_amount']);
        $this->assertEquals(15600, $result['gross_amount']);
    }

    public function test_explicit_none_surcharge(): void
    {
        $repo = new PaymentRepository();
        $repo->value = [];

        $request = new Request([
            'paymentAmount' => 100000,
            'surchargeMode' => 'none',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveSurcharge');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $request, $repo, 100000, 'idr');

        $this->assertEquals('none', $result['mode']);
        $this->assertEquals(0, $result['fee_amount']);
        $this->assertEquals(10000000, $result['gross_amount']);
    }

    public function test_middleware_calc_surcharge_via_request(): void
    {
        $repo = new PaymentRepository();
        $repo->value = [];

        $request = new Request([
            'paymentAmount' => 100000,
            'surchargeMode' => 'middleware_calc',
            'surchargePercent' => 2.9,
            'surchargeFixed' => 2000,
            'surchargeLabel' => 'Processing Fee',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveSurcharge');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $request, $repo, 100000, 'idr');

        $this->assertEquals('middleware_calc', $result['mode']);
        $this->assertEquals('Processing Fee', $result['label']);
        // Net = 100000, Fixed = 2000, Percent = 2.9% -> (102000 / 0.971) = ceil(105046.34) = 105047
        // Fee raw = 105047 - 100000 = 5047
        $this->assertEquals(5047, $result['fee_amount_raw']);
        // in cents (x100 for IDR)
        $this->assertEquals(504700, $result['fee_amount']);
        $this->assertEquals(10504700, $result['gross_amount']);
    }

    public function test_middleware_calc_surcharge_via_repo_config(): void
    {
        $repo = new PaymentRepository();
        $repo->value = [
            'surcharge_mode' => 'middleware_calc',
            'surcharge_percent' => 2.9,
            'surcharge_fixed' => 0.30,
            'surcharge_label' => 'Credit Card Fee',
        ];

        $request = new Request([
            'paymentAmount' => 100,
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveSurcharge');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $request, $repo, 100, 'usd');

        $this->assertEquals('middleware_calc', $result['mode']);
        $this->assertEquals('Credit Card Fee', $result['label']);
        // Gross raw: ceil((100 + 0.30) / 0.971) = ceil(103.2955) = 104
        $this->assertEquals(4, $result['fee_amount_raw']);
        $this->assertEquals(400, $result['fee_amount']);
        $this->assertEquals(10400, $result['gross_amount']);
    }

    public function test_stripe_automatic_surcharge(): void
    {
        $repo = new PaymentRepository();
        $repo->value = [
            'surcharge_mode' => 'stripe_automatic',
        ];

        $request = new Request([
            'paymentAmount' => 100,
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveSurcharge');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $request, $repo, 100, 'usd');

        $this->assertEquals('stripe_automatic', $result['mode']);
        $this->assertEquals(0, $result['fee_amount']);
        $this->assertEquals(10000, $result['gross_amount']);
    }
}
