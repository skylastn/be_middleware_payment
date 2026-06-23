<?php

namespace App\Http\Middleware;

use App\Model\Entity\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class LogViewerTokenAuth
{
    public const COOKIE_NAME = 'log_viewer_token';

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $token = $request->bearerToken()
            ?? $request->query('token')
            ?? $request->cookie(self::COOKIE_NAME);

        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken && $accessToken->tokenable instanceof User && $accessToken->tokenable->isAdmin()) {
                Auth::setUser($accessToken->tokenable);

                if ($request->query('token') && ! $request->cookie(self::COOKIE_NAME)) {
                    $response = $next($request);

                    return $response->withCookie(cookie(
                        self::COOKIE_NAME,
                        $token,
                        1440,
                        '/',
                        null,
                        $request->secure(),
                        true,
                    ));
                }

                return $next($request);
            }
        }

        if ($request->expectsJson() || $request->is('log-viewer/api/*')) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return redirect()->route('login');
    }
}
