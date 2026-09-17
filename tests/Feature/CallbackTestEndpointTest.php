<?php

namespace Tests\Feature;

use Tests\TestCase;

class CallbackTestEndpointTest extends TestCase
{
    public function test_post_callback_test_returns_success(): void
    {
        $payload = [
            'merchantOrderId' => 'TEST-12345',
            'resultCode' => '00',
            'amount' => 50000,
            'reference' => 'REF-999',
        ];

        $response = $this->postJson('/api/callback/test', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Success Callback Test');
        $response->assertJsonPath('data.received', true);
        $response->assertJsonPath('data.method', 'POST');
        $response->assertJsonPath('data.data.merchantOrderId', 'TEST-12345');
    }

    public function test_get_callback_test_returns_success(): void
    {
        $response = $this->getJson('/api/callback/test?reference=TEST-123');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.received', true);
        $response->assertJsonPath('data.method', 'GET');
    }
}
