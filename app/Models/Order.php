<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $casts = ['id' => 'string'];
    protected $fillable = ['id', 'mode', 'type', "reference", "payment_method", 'request', 'response', "callback", "url", 'notes', 'address', 'phone', 'email'];
    protected $with = ['payment_methods', 'project'];

    function payment_methods()
    {
        return $this->hasOne(PaymentMethod::class, 'value', 'payment_method');
    }

    function project()
    {
        return $this->hasOne(Project::class, 'type', 'type');
    }
}
