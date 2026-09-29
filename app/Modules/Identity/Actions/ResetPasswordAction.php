<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\PasswordResetLinkInvalidException;
use App\Modules\Identity\Data\PasswordResetData;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountEligibilityService;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Password;

final class ResetPasswordAction
{
    public function __construct(
        private AccountEligibilityService $eligibility,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(PasswordResetData $reset): void
    {
        $this->companyContext->withoutIsolation(function () use ($reset): void {
            $user = User::findByEmail($reset->email);

            throw_if(
                ! $user instanceof User || ! $this->eligibility->isEligible($user),
                PasswordResetLinkInvalidException::class,
            );

            $status = Password::broker()->reset(
                [
                    'email' => $user->email,
                    'password' => $reset->password,
                    'token' => $reset->token,
                ],
                fn (mixed $account, string $newPassword) => $this->applyNewPassword($account, $newPassword),
            );

            throw_unless($status === Password::PASSWORD_RESET, PasswordResetLinkInvalidException::class);
        });
    }

    private function applyNewPassword(mixed $account, string $password): void
    {
        throw_unless($account instanceof User, PasswordResetLinkInvalidException::class);

        $account->forceFill([
            'password' => $password,
            'session_version' => $account->session_version + 1,
        ])->save();
    }
}
