<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGateway extends Model
{
    use SoftDeletes, HasUuids;
    protected $table = 'payment_gateways';
    protected $fillable = [
        'key',
        'name',
        'description',
    ];
    protected $casts = [
        'id' => 'string',
    ];
}
