<?php

namespace App\Services\Payment;

use App\Http\Helper\RequestHelper;
use App\Jobs\DeliverMerchantCallback;
use App\Model\Entity\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CallbackDeliveryService
{
    public function enqueue(Project $project, string $reference, array $payload): void
    {
        $key = hash('sha256', (string) $project->id.'|'.$reference.'|'.json_encode($payload));
        $id = (string) Str::uuid();
        $created = DB::table('merchant_callback_deliveries')->insertOrIgnore([
            'id' => $id, 'deduplication_key' => $key, 'project_id' => (string) $project->id,
            'reference' => $reference, 'payload' => json_encode($payload),
            'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($created) {
            DB::afterCommit(function () use ($id) {
                try {
                    DeliverMerchantCallback::dispatch($id)->onQueue('payment-callback');
                } catch (Throwable $e) {
                    Log::warning('Callback queued for recovery', ['delivery_id' => $id]);
                }
            });
        }
    }

    public function deliver(string $id): void
    {
        $delivery = DB::transaction(function () use ($id) {
            $row = DB::table('merchant_callback_deliveries')->where('id', $id)->lockForUpdate()->first();
            if (! $row || $row->delivered_at || ($row->leased_until && now()->lt($row->leased_until))) {
                return null;
            }
            DB::table('merchant_callback_deliveries')->where('id', $id)->update([
                'attempts' => $row->attempts + 1, 'leased_until' => now()->addSeconds(60), 'updated_at' => now(),
            ]);
            return $row;
        });
        if (! $delivery) {
            return;
        }
        try {
            $project = Project::findOrFail($delivery->project_id);
            RequestHelper::sendCallback($project->value, json_decode($delivery->payload, true), $project->callback);
            DB::table('merchant_callback_deliveries')->where('id', $id)->update([
                'delivered_at' => now(), 'leased_until' => null, 'last_error' => null, 'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            DB::table('merchant_callback_deliveries')->where('id', $id)->update([
                'leased_until' => null, 'available_at' => now()->addSeconds(min(3600, 10 * (2 ** min(8, $delivery->attempts)))),
                'last_error' => get_class($e), 'updated_at' => now(),
            ]);
            throw $e;
        }
    }

    public function recover(): void
    {
        $ids = DB::transaction(function () {
            $ids = DB::table('merchant_callback_deliveries')->whereNull('delivered_at')
                ->where('available_at', '<=', now())
                ->where(fn ($q) => $q->whereNull('leased_until')->orWhere('leased_until', '<=', now()))
                ->orderBy('available_at')->limit(100)->lockForUpdate()->pluck('id');
            DB::table('merchant_callback_deliveries')->whereIn('id', $ids)->update(['available_at' => now()->addMinutes(2)]);
            return $ids;
        });
        foreach ($ids as $id) {
            DeliverMerchantCallback::dispatch($id)->onQueue('payment-callback');
        }
    }
}
