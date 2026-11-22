<?php

namespace App\Services\System;

use App\Model\Entity\User;
use App\Repository\System\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthService
{
    private UserRepository $userRepository;

    public function __construct(?UserRepository $userRepository = null)
    {
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    /**
     * Authenticate admin credentials and generate Sanctum personal access token.
     *
     * @param string $email
     * @param string $password
     * @return array{user: User, token: string, expires_at: \Carbon\CarbonInterface, ttl_minutes: int}
     * @throws ValidationException
     */
    public function attemptAdminLogin(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password) || ! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['Invalid admin credentials.'],
            ]);
        }

        // Revoke any prior backoffice token so a re-login (or a leaked old token)
        // cannot stay valid past the new session. Only one backoffice session
        // is active per user at a time.
        $user->tokens()->where('name', 'backoffice')->delete();

        // Hard session lifetime: after this the token stops authorizing and
        // Sanctum returns 401, so a stolen token is only useful for this window.
        $ttlMinutes = max(1, (int) env('BACKOFFICE_TOKEN_TTL_MINUTES', 2880));
        $expiration = now()->addMinutes($ttlMinutes);

        $token = $user->createToken('backoffice', ['*'], $expiration)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'expires_at' => $expiration,
            'ttl_minutes' => $ttlMinutes,
        ];
    }

    /**
     * Revoke current access token for authenticated user.
     *
     * @param User|null $user
     * @return void
     */
    public function logout(?User $user): void
    {
        $user?->currentAccessToken()?->delete();
    }
}
