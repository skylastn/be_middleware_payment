<?php

namespace App\Models;

use App\Enums\PaymentModeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRepository extends Model
{
    use SoftDeletes, HasUuids;
    protected $table = 'payment_repositories';
    protected $fillable = [
        'payment_gateway_id',
        'mode',
        'value',
    ];
    protected $casts = [
        'id' => 'string',
        'payment_gateway_id' => 'string',
        'mode' => PaymentModeType::class
    ];
    protected $with = ['paymentGateway'];

    public function paymentGateway(): HasOne
    {
        return $this->hasOne(PaymentGateway::class, 'id', 'payment_gateway_id');
    }
}
