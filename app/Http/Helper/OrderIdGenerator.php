<?php

namespace App\Http\Helper;

use Illuminate\Support\Facades\Redis;

class OrderIdGenerator
{
    public static function generate(): string
    {
        $date = date('Ymd');
        $key = "order:counter:{$date}";

        $sequence = Redis::incr($key);

        if ($sequence === 1) {
            Redis::expire($key, 172800);
        }

        return $date.'-'.str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }
}
