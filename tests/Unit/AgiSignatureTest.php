<?php

namespace Tests\Unit;

use App\Enums\ProjectSlug;
use App\Services\Payment\AgiService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AgiSignatureTest extends TestCase
{
    public function test_symmetric_signature_uses_decoded_secret_and_lowercase_hex(): void
    {
        $service = new AgiService;
        $signature = $service->signSymmetric(
            'YWdpLXRlc3Qtc2VjcmV0',
            'POST',
            '/snap/api/v1.0/qr/qr-mpm-generate',
            'token-123',
            "{\n  \"hello\": \"Bank Artha Graha\"\n}",
            '2025-07-28T09:57:26+07:00',
        );

        $this->assertSame('5601c1391e78622c07814360fec3b8aadd4acdc0065beaac380e44d0442760d8a894098e15368d8520ce8ca7760d49905b72e3fbce09331aae8506cd90113acb', $signature);
    }

    public function test_minification_preserves_escaped_quotes_unicode_and_spaces_inside_strings(): void
    {
        $service = new AgiService;
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
        $expected = hash_hmac('sha512', 'POST:/notify:token:'.hash('sha256', $body).':timestamp', 'secret');

        $this->assertSame($expected, $service->signSymmetric(base64_encode('secret'), 'post', '/notify', 'token', $prettyBody, 'timestamp'));
    }

    public function test_invalid_base64_secret_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new AgiService)->signSymmetric('not!base64', 'POST', '/notify', 'token', '{}', 'timestamp');
    }

    public function test_asymmetric_signature_is_verified_with_the_public_key(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $timestamp = '2025-07-28T09:57:26+07:00';
        $signature = (new AgiService)->signAsymmetric('client-id', $timestamp, str_replace("\n", '\\n', $privateKey));

        $this->assertSame(1, openssl_verify('client-id|'.$timestamp, base64_decode($signature), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256));
    }

    public function test_agi_slug_accepts_case_insensitive_aliases(): void
    {
        $this->assertSame(ProjectSlug::AGI, ProjectSlug::fromName('AGI'));
        $this->assertSame(ProjectSlug::AGI, ProjectSlug::fromName('BANK-ARTHA-GRAHA'));
        $this->assertSame(ProjectSlug::AGI, ProjectSlug::fromName('BANK_AGI'));
        $this->assertContains('bank_agi', ProjectSlug::values());
    }
}
