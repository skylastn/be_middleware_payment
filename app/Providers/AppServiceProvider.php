<?php

namespace App\Providers;

use App\Model\Entity\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request as HttpRequest;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('viewLogViewer', function (?User $user): bool {
            if ($user && $user->isAdmin()) {
                return true;
            }

            $tokenUser = Auth::guard('sanctum')->user();
            if ($tokenUser instanceof User && $tokenUser->isAdmin()) {
                return true;
            }

            $token = HttpRequest::bearerToken() ?? HttpRequest::query('token');
            if ($token) {
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken && $accessToken->tokenable instanceof User && $accessToken->tokenable->isAdmin()) {
                    Auth::guard('sanctum')->setUser($accessToken->tokenable);

                    return true;
                }
            }

            return false;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
