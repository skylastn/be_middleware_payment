<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Entity\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->isAdmin()) {
            return response()->json([
                'message' => 'Invalid admin credentials.',
                'errors' => ['email' => ['Invalid admin credentials.']],
            ], 422);
        }

        $token = $user->createToken('backoffice')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ])->withCookie(cookie(
            'log_viewer_token',
            $token,
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
            'user' => [
                'name' => $user?->name,
                'email' => $user?->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
