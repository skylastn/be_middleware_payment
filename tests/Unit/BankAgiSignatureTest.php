<?php

namespace Tests\Unit;

use App\Enums\ProjectSlug;
use App\Services\Payment\BankAgiService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BankAgiSignatureTest extends TestCase
{
    public function test_symmetric_signature_uses_decoded_secret_and_lowercase_hex(): void
    {
        $service = new BankAgiService;
        $signature = $service->signSymmetric(
            'YWdpLXRlc3Qtc2VjcmV0',
            'POST',
            '/snap/api/v1.0/qr/qr-mpm-generate',
            'token-123',
            "{\n  \"hello\": \"Bank Artha Graha\"\n}",
            '2025-07-28T09:57:26+07:00',
        );

        $this->assertSame('09a475b243317f28893b337e8a5e3ec557144334e4bfcda3c8d4e515c6573b6e7654e0d879f7f256fc6a9f1c45d03d39e8f849ee448a9a8d5fd74bf8f523da81', $signature);
    }

    public function test_whitespace_is_completely_removed_before_hashing(): void
    {
        $service = new BankAgiService;
        $body = <<<'JSON'
{"name":"Artha  Graha","quote":"a\" b","url":"https:\/\/ag.co.id","unicode":"\u0041"}
JSON;
        $prettyBody = <<<'JSON'
{
  "name": "Artha  Graha",
  "quote": "a\" b",
  "url": "https:\/\/ag.co.id",
  "unicode": "\u0041"
}
JSON;
        $cleaned = preg_replace('/\s+/', '', $body);
        $expected = hash_hmac('sha512', 'POST:/notify:token:'.hash('sha256', $cleaned).':timestamp', 'secret');

        $this->assertSame($expected, $service->signSymmetric(base64_encode('secret'), 'post', '/notify', 'token', $prettyBody, 'timestamp'));
    }

    public function test_invalid_base64_secret_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new BankAgiService)->signSymmetric('not!base64', 'POST', '/notify', 'token', '{}', 'timestamp');
    }

    public function test_asymmetric_signature_is_verified_with_the_public_key(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $timestamp = '2025-07-28T09:57:26+07:00';
        $signature = (new BankAgiService)->signAsymmetric('client-id', $timestamp, str_replace("\n", '\\n', $privateKey));

        $this->assertSame(1, openssl_verify('client-id|'.$timestamp, base64_decode($signature), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256));
    }

    public function test_agi_slug_accepts_case_insensitive_aliases(): void
    {
        $this->assertSame(ProjectSlug::BANK_AGI, ProjectSlug::fromName('AGI'));
        $this->assertSame(ProjectSlug::BANK_AGI, ProjectSlug::fromName('BANK-ARTHA-GRAHA'));
        $this->assertSame(ProjectSlug::BANK_AGI, ProjectSlug::fromName('BANK_AGI'));
        $this->assertContains('bank_agi', ProjectSlug::values());
    }
}
