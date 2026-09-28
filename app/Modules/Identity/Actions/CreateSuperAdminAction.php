<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;

final class CreateSuperAdminAction
{
    public function __invoke(string $email, string $password): User
    {
        $email = mb_strtolower($email);

        throw_if(User::emailIsRegistered($email), EmailAlreadyRegisteredException::class);

        return User::query()->create([
            'email' => $email,
            'password' => $password,
            'role' => Role::SuperAdmin,
            'company_id' => null,
        ]);
    }
}
