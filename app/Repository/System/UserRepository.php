<?php

namespace App\Repository\System;

use App\Model\Entity\User;
use App\Repository\BaseRepository;

class UserRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return User::class;
    }
    
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
