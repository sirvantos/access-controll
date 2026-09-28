<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountEligibilityService;
use Illuminate\Support\Facades\Password;

final class RequestPasswordResetAction
{
    public function __construct(private AccountEligibilityService $eligibility) {}

    public function __invoke(string $email): void
    {
        $user = User::findByEmail($email);

        if (! $user instanceof User || ! $this->eligibility->isEligible($user)) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $user->email]);
    }
}
