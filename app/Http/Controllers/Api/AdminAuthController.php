<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Request\Auth\LoginRequest;
use App\Model\Response\Auth\UserResource;
use App\Services\System\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    private AdminAuthService $authService;

    public function __construct(?AdminAuthService $authService = null)
    {
        $this->authService = $authService ?? new AdminAuthService();
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $authData = $this->authService->attemptAdminLogin($credentials['email'], $credentials['password']);

        return response()->json([
            'token' => $authData['token'],
            'user' => new UserResource($authData['user']),
        ])->withCookie(cookie(
            'log_viewer_token',
            $authData['token'],
            1440,
            '/',
            null,
            $request->secure(),
            true,
        ));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? new UserResource($user) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
