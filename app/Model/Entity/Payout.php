<?php

namespace App\Model\Entity;

use App\Enums\PayoutGateway;
use App\Enums\TransferStatus;
use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use BaseModelTrait, HasUuids;

    protected $table = 'payouts';

    protected $fillable = [
        'amount',
        'gateway',
        'currency',
        'status',
        'reference',
        'internal_id',
        'request',
        'response',
        'callback',
        'completed_at',
    ];

    protected $casts = [
        'id' => 'string',
        'gateway' => PayoutGateway::class,
        'amount' => 'decimal:2',
        'currency' => 'string',
        'status' => TransferStatus::class,
        'reference' => 'string',
        'internal_id' => 'string',
        'request' => 'string',
        'response' => 'string',
        'callback' => 'string',
        'completed_at' => 'datetime',
    ];

    public function histories(): HasMany
    {
        return $this->hasMany(PayoutHistory::class)->orderBy('performed_at');
    }
}
