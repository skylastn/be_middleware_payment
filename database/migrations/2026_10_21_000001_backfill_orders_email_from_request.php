<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHUNK = 500;

    public function up(): void
    {
        DB::table('orders')
            ->select(['id', 'request'])
            ->whereNull('email')
            ->whereNotNull('request')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($orders) {
                foreach ($orders as $order) {
                    $email = $this->extractEmail($order->request);

                    if ($email === null) {
                        continue;
                    }

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->whereNull('email')
                        ->update(['email' => $email]);
                }
            }, 'id');
    }

    public function down(): void
    {
        //
    }

    private function extractEmail(?string $request): ?string
    {
        if ($request === null || $request === '') {
            return null;
        }

        $payload = json_decode($request, true);

        if (! is_array($payload)) {
            return null;
        }

        return $this->firstEmail([
            $payload['email'] ?? null,
            $payload['customerDetail']['email'] ?? null,
            $payload['customer_details']['email'] ?? null,
            $payload['additionalInfo']['email'] ?? null,
        ]);
    }

    private function firstEmail(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }
};
