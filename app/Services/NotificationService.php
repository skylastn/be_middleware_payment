<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Socket\MiddlewareSocketService;

class NotificationService
{
    public static function sendNotification($reference): void
    {
        $project = 'payment';
        $path = 'notification';
        $content = Order::where('reference', $reference)->latest()->first();
        $body = [
            'type' => 'order',
            'data' => $content,
        ];
        (new MiddlewareSocketService($project, $path, $body))->sendNotif();
    }
}
