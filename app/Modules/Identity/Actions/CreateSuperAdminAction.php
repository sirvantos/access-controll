<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Tenancy\PublicApi\CompanyContext;

final class CreateSuperAdminAction
{
    public function __construct(private CompanyContext $companyContext) {}

    public function __invoke(string $email, string $password): User
    {
        $email = mb_strtolower($email);

        throw_if(User::emailIsRegistered($email), EmailAlreadyRegisteredException::class);

        return $this->companyContext->withoutIsolation(
            fn (): User => User::query()->create([
                'email' => $email,
                'password' => $password,
                'role' => Role::SuperAdmin,
                'company_id' => null,
            ]),
        );
    }
}
