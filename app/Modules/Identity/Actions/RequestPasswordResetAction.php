<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountEligibilityService;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Stringable;

final class RequestPasswordResetAction
{
    public function __construct(
        private AccountEligibilityService $eligibility,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(Stringable $email): void
    {
        $this->companyContext->withoutIsolation(function () use ($email): void {
            $user = User::findByEmail($email);

            if (! $user instanceof User || ! $this->eligibility->isEligible($user)) {
                return;
            }

            Password::broker()->sendResetLink(['email' => $user->email]);
        });
    }
}
