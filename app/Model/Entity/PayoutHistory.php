<?php

namespace App\Model\Entity;

use App\Traits\BaseModelTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutHistory extends Model
{
    use BaseModelTrait, HasUuids;

    protected $table = 'payout_histories';

    public $timestamps = false;

    protected $fillable = [
        'payout_id',
        'action',
        'status',
        'message',
        'metadata',
        'performed_at',
    ];

    protected $casts = [
        'id' => 'string',
        'payout_id' => 'string',
        'metadata' => 'array',
        'performed_at' => 'datetime',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
