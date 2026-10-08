<?php

namespace Tests\Unit;

use App\Http\Helper\LogHelper;
use App\Providers\TelescopeServiceProvider;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\ClientRequestWatcher;
use Laravel\Telescope\Watchers\RequestWatcher;
use ReflectionMethod;
use Tests\TestCase;

class BankAgiLogRedactionTest extends TestCase
{
    public function test_failed_bank_response_logs_safe_diagnostics(): void
    {
        $context = ['endpoint' => '/qr/qr-mpm-generate', 'external_id' => 'test-id'];
        \Illuminate\Support\Facades\Log::shouldReceive('error')->once()->with('AGI request failed', $context + [
            'expected_code' => '2001700',
            'response_code' => '5001700',
            'response_message' => 'Internal Server error',
        ]);
        $service = (new \ReflectionClass(\App\Services\Payment\BankAgiService::class))->newInstanceWithoutConstructor();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AGI request failed: Internal Server error');
        (new ReflectionMethod($service, 'decodeResponse'))->invoke($service, json_encode([
            'responseCode' => '5001700', 'responseMessage' => 'Internal Server error', 'accessToken' => 'must-not-be-logged',
        ]), '2001700', $context);
    }

    public function test_bank_credentials_are_redacted_in_nested_json_logs(): void
    {
        $data = ['Authorization' => 'Bearer token', 'nested' => json_encode([
            'private_key' => 'private-key', 'client_secret' => 'secret', 'bank_client_secret' => 'bank-secret', 'accessToken' => 'access-token', 'X_SIGNATURE' => 'signature', 'merchant_id' => 'merchant',
        ])];
        $redacted = LogHelper::redactSensitiveData($data);
        $this->assertSame('***REDACTED***', $redacted['Authorization']);
        $nested = json_decode($redacted['nested'], true);
        foreach (['private_key', 'client_secret', 'bank_client_secret', 'accessToken', 'X_SIGNATURE'] as $key) {
            $this->assertSame('***REDACTED***', $nested[$key]);
        }
        $this->assertSame('merchant', $nested['merchant_id']);
    }

    public function test_telescope_hides_bank_headers_and_tokens_even_in_local_environment(): void
    {
        $this->app->instance('env', 'local');
        $provider = new TelescopeServiceProvider($this->app);
        (new ReflectionMethod($provider, 'hideSensitiveRequestDetails'))->invoke($provider);
        $client = new ClientRequestWatcher([]);
        $headers = (new ReflectionMethod($client, 'headers'))->invoke($client, ['Authorization' => ['Bearer token'], 'X_SIGNATURE' => ['signature']]);
        $this->assertSame('********', $headers['authorization']);
        $this->assertSame('********', $headers['x_signature']);
        $response = new Response(new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode(['accessToken' => 'bank-token'])));
        $this->assertSame('********', (new ReflectionMethod($client, 'response'))->invoke($client, $response)['accessToken']);
        $inbound = new RequestWatcher([]);
        $payload = (new ReflectionMethod($inbound, 'response'))->invoke($inbound, response()->json(['accessToken' => 'callback-token', 'data' => ['value' => ['private_key' => 'secret']]]));
        $this->assertSame('********', $payload['accessToken']);
        $this->assertSame('********', $payload['data']['value']);
        $this->assertContains('value', Telescope::$hiddenRequestParameters);
    }

    public function test_telescope_does_not_store_tokens_from_redis_commands_or_credential_queries(): void
    {
        $this->app->instance('env', 'local');
        (new TelescopeServiceProvider($this->app))->register();
        $filter = Telescope::$filterUsing[array_key_last(Telescope::$filterUsing)];
        foreach (['agi:snap_token:', 'bank_agi:outbound_token:', 'payment_token:', 'project:token:'] as $namespace) {
            $this->assertFalse($filter(IncomingEntry::make(['command' => 'setex '.$namespace.'key 900 secret'])->type('redis')));
        }
        $this->assertFalse($filter(IncomingEntry::make(['sql' => 'update payment_repositories set value = secret'])->type('query')));
        $this->assertFalse($filter(IncomingEntry::make(['sql' => 'select * from projects where value = token'])->type('query')));
        $this->assertTrue($filter(IncomingEntry::make(['sql' => 'select * from orders'])->type('query')));
    }
}
