<?php

namespace App\Providers;

use App\Interface\RabbitMQServiceInterface;
use App\Interface\RedisServiceInterface;
use App\Model\Entity\User;
use App\Services\System\RabbitMQService;
use App\Services\System\RedisService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RedisServiceInterface::class, RedisService::class);
        $this->app->singleton(RedisService::class, RedisService::class);

        $this->app->singleton(RabbitMQServiceInterface::class, RabbitMQService::class);
        $this->app->singleton(RabbitMQService::class, RabbitMQService::class);
    }

    public function boot(): void
    {
        Gate::define('viewLogViewer', function (?User $user): bool {
            return $user instanceof User && $user->isAdmin();
        });

        // Ensure LogViewer does not truncate log entries up to 10MB
        LogViewer::setMaxLogSize(10 * 1024 * 1024);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
