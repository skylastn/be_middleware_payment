<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Enums\UserRole;
use App\Model\Entity\Order;
use App\Model\Entity\Project;
use App\Model\Entity\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderFilterApiTest extends TestCase
{
    private Project $project;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::firstOrCreate(
            ['type' => 'OFT'],
            [
                'name' => 'Order Filter Test',
                'slug' => 'duitku',
                'key' => 'oftkey123',
                'secure' => 'oftsecure123',
                'value' => 'oft_token_' . Str::random(32),
                'callback' => 'https://example.com/callback',
            ]
        );

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_filter@example.com'],
            [
                'name' => 'Admin Filter Test',
                'password' => bcrypt('password'),
                'role' => UserRole::ADMIN,
            ]
        );

        Order::where('type', 'OFT')->delete();

        $this->createOrder('OFT-001', 'REF-OFT-001', 'alice@example.com', '2026-10-13 09:15:00');
        $this->createOrder('OFT-002', 'REF-OFT-002', 'bob@example.com', '2026-10-13 14:30:00');
        $this->createOrder('OFT-003', 'REF-OFT-003', 'charlie@domain.org', '2026-10-13 21:45:00');
    }

    protected function tearDown(): void
    {
        Order::where('type', 'OFT')->delete();
        parent::tearDown();
    }

    private function createOrder(string $id, string $ref, string $email, string $createdAt): Order
    {
        $order = Order::create([
            'id' => $id,
            'type' => 'OFT',
            'reference' => $ref,
            'payment_method' => 'BC',
            'email' => $email,
            'status' => OrderStatus::PENDING->value,
            'mode' => PaymentModeType::sandbox->value,
            'amount' => 10000,
        ]);
        $order->timestamps = false;
        $order->created_at = Carbon::parse($createdAt);
        $order->updated_at = Carbon::parse($createdAt);
        $order->save();

        return $order;
    }

    public function test_client_api_filters_by_email(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email=alice@example.com')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('REF-OFT-001', $data[0]['reference']);
    }

    public function test_client_api_filters_by_multiple_emails_comma_separated(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email=alice@example.com,charlie@domain.org')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-003', $references);
        $this->assertNotContains('REF-OFT-002', $references);
    }

    public function test_client_api_multiple_emails_accepts_array_param(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email[]=alice@example.com&email[]=bob@example.com')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-002', $references);
        $this->assertNotContains('REF-OFT-003', $references);
    }

    public function test_client_api_multiple_emails_ignore_blanks(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email= alice@example.com ,, charlie@domain.org ,')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-003', $references);
        $this->assertNotContains('REF-OFT-002', $references);
    }

    public function test_client_api_multiple_emails_use_partial_match(): void
    {
        // "example.com" must match both alice and bob, but not charlie.
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email=example.com')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-002', $references);
        $this->assertNotContains('REF-OFT-003', $references);
    }

    public function test_client_api_multiple_emails_compose_with_hour_filter(): void
    {
        // OR across emails must still AND with the other filters.
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email=alice@example.com,bob@example.com&hour=14')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(1, $references);
        $this->assertSame('REF-OFT-002', $references[0]);
    }

    public function test_client_api_multiple_emails_apply_to_summary(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order/summary?email=alice@example.com,charlie@domain.org')
            ->assertOk();

        $this->assertSame(2, $response->json('data.total_orders'));
    }

    public function test_admin_api_filters_by_multiple_emails(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/orders?email=bob@example.com,charlie@domain.org')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-002', $references);
        $this->assertContains('REF-OFT-003', $references);
        $this->assertNotContains('REF-OFT-001', $references);
    }

    public function test_client_api_filters_by_single_hour(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?hour=14')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('REF-OFT-002', $data[0]['reference']);
    }

    public function test_client_api_filters_by_jam_alias(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?jam=9')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('REF-OFT-001', $data[0]['reference']);
    }

    public function test_client_api_filters_by_time_range(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?start_time=08:00&end_time=15:00')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $references = array_column($data, 'reference');
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-002', $references);
    }

    public function test_client_api_filters_by_email_and_hour_combined(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email=bob&hour=14')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('REF-OFT-002', $data[0]['reference']);

        $mismatch = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?email=bob&hour=9')
            ->assertOk();
        $this->assertCount(0, $mismatch->json('data'));
    }

    public function test_auth_admin_api_filters_by_email(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/orders?email=charlie@domain.org')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('REF-OFT-003', $data[0]['reference']);
    }

    public function test_auth_admin_api_filters_by_hour(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/orders?type=OFT&hour=21')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('REF-OFT-003', $data[0]['reference']);
    }

    public function test_client_api_summary_respects_filters(): void
    {
        $all = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order/summary')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertSame(3, $all->json('data.total_orders'));
        $this->assertEquals(30000.0, $all->json('data.total_amount'));
        $this->assertEquals(10000.0, $all->json('data.average_amount'));

        $filtered = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order/summary?hour=14')
            ->assertOk();

        $this->assertSame(1, $filtered->json('data.total_orders'));
        $this->assertEquals(10000.0, $filtered->json('data.total_amount'));

        $byEmail = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order/summary?email=alice')
            ->assertOk();

        $this->assertSame(1, $byEmail->json('data.total_orders'));
        $this->assertEquals(10000.0, $byEmail->json('data.total_amount'));
    }

    public function test_client_api_summary_breaks_down_by_status(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order/summary')
            ->assertOk();

        $breakdown = $response->json('data.status_breakdown');
        $this->assertArrayHasKey('PENDING', $breakdown);
        $this->assertSame(3, $breakdown['PENDING']['count']);
        $this->assertEquals(30000.0, $breakdown['PENDING']['amount']);
    }

    public function test_admin_api_summary_respects_filters(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/orders/summary?type=OFT&hour=21')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertSame(1, $response->json('data.total_orders'));
        $this->assertEquals(10000.0, $response->json('data.total_amount'));

        $empty = $this->getJson('/api/admin/orders/summary?type=OFT&email=nobody@example.com')
            ->assertOk();

        $this->assertSame(0, $empty->json('data.total_orders'));
        $this->assertEquals(0.0, $empty->json('data.total_amount'));
        $this->assertEquals(0.0, $empty->json('data.average_amount'));
    }

    public function test_client_api_filters_by_hour_range(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?hour=09-15')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-002', $references);
    }

    public function test_client_api_hour_filter_excludes_orders_outside_the_hour(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?hour=09')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertSame(['REF-OFT-001'], $references);
    }

    public function test_client_api_time_range_bounds_are_inclusive(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?start_time=09:15&end_time=14:30')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-002', $references);
    }

    public function test_client_api_start_time_alone_is_an_open_ended_lower_bound(): void
    {
        // Orders sit at 09:15, 14:30 and 21:45. A lone start_time must mean
        // "at or after this time", not "exactly this time".
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?start_time=14:30')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-002', $references);
        $this->assertContains('REF-OFT-003', $references);
        $this->assertNotContains('REF-OFT-001', $references);
    }

    public function test_client_api_end_time_alone_is_an_open_ended_upper_bound(): void
    {
        $response = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order?end_time=14:30')
            ->assertOk();

        $references = array_column($response->json('data'), 'reference');
        $this->assertCount(2, $references);
        $this->assertContains('REF-OFT-001', $references);
        $this->assertContains('REF-OFT-002', $references);
        $this->assertNotContains('REF-OFT-003', $references);
    }

    public function test_client_api_summary_ignores_pagination_params(): void
    {
        $paged = $this->withHeader('Token', $this->project->value)
            ->getJson('/api/order/summary?page=2&per_page=1')
            ->assertOk();

        $this->assertSame(3, $paged->json('data.total_orders'));
        $this->assertEquals(30000.0, $paged->json('data.total_amount'));
    }

    public function test_summary_normalizes_a_null_status_to_unknown(): void
    {
        $order = Order::create([
            'id' => 'ORDER-OFT-NULL',
            'type' => 'OFT',
            'reference' => 'REF-OFT-NULL',
            'payment_method' => 'BC',
            'email' => 'null@example.com',
            'status' => null,
            'mode' => PaymentModeType::sandbox->value,
            'amount' => 5000,
        ]);

        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/admin/orders/summary?type=OFT&email=null@example.com')
            ->assertOk();

        $breakdown = $response->json('data.status_breakdown');
        $this->assertArrayHasKey('UNKNOWN', $breakdown);
        $this->assertArrayNotHasKey('', $breakdown);
        $this->assertSame(1, $breakdown['UNKNOWN']['count']);

        $order->delete();
    }
}
