<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = ['id', 'mode' ,'type', "reference", "payment_method", 'request', 'response', "callback", "url" ];
}
