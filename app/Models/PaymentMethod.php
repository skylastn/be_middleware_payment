<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;
    protected $fillable = ['id', 'key' ,'value', "type", 'from', 'name'];
    protected $with = ['category'];

    function category() {
        return $this->hasOne(PaymentCategory::class, 'key', 'key');
    }
}
