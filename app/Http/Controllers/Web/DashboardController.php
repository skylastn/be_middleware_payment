<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Model\Entity\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class DashboardController extends Controller
{
    public const COOKIE_NAME = 'log_viewer_token';

    public function index(): View
    {
        return view('backoffice');
    }

    public function showLogin(): View|RedirectResponse
    {
        if ($this->hasValidToken(request())) {
            return redirect('/log-viewer');
        }

        return view('auth.login');
    }

    public function storeLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->isAdmin()) {
            return back()->withErrors([
                'email' => 'Invalid admin credentials.',
            ])->onlyInput('email');
        }

        $token = $user->createToken('log-viewer')->plainTextToken;

        return redirect('/log-viewer')
            ->withCookie(cookie(
                self::COOKIE_NAME,
                $token,
                1440,
                '/',
                null,
                $request->secure(),
                true,
            ));
    }

    public function logout(Request $request): RedirectResponse
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $accessToken->delete();
            }
        }

        return redirect()->route('login')
            ->withoutCookie(self::COOKIE_NAME);
    }

    private function hasValidToken(Request $request): bool
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! $token) {
            return false;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken
            && $accessToken->tokenable instanceof User
            && $accessToken->tokenable->isAdmin();
    }
}
