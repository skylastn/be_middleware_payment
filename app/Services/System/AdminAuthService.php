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
     * @return array{user: User, token: string}
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

        $token = $user->createToken('backoffice')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
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
