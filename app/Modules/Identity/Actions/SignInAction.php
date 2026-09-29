<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Exceptions\SignInBlockedException;
use App\Exceptions\SignInFailedException;
use App\Modules\Identity\Data\SignInAttempt;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\Services\AccountEligibilityService;
use App\Modules\Identity\Services\SignInThrottleService;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Stringable;
use Illuminate\Support\Timebox;

final class SignInAction
{
    /**
     * Same default duration as SessionGuard::attempt.
     */
    private const int SIGN_IN_TIMEBOX_MICROSECONDS = 200_000;

    /**
     * Fixed bcrypt hash so an unknown email still pays for one Hash::check.
     */
    private const string UNKNOWN_EMAIL_PASSWORD_HASH = '$2y$12$icInfln87iPbIcXgHfK0d.74.iQ.tNPlpkwlcUYt6muCa6FDuzjL2';

    public function __construct(
        private SignInThrottleService $throttle,
        private AccountEligibilityService $eligibility,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(SignInAttempt $attempt): Actor
    {
        return new Timebox()->call(
            fn (Timebox $timebox): Actor => $this->attempt($attempt, $timebox),
            self::SIGN_IN_TIMEBOX_MICROSECONDS,
        );
    }

    private function attempt(SignInAttempt $attempt, Timebox $timebox): Actor
    {
        $user = $this->companyContext->withoutIsolation(
            fn (): ?User => User::query()->where('email', $attempt->email)->first(),
        );
        $userId = $user instanceof User ? $user->id : null;

        throw_if(
            $this->throttle->isBlocked($attempt->email, $attempt->ip),
            fn (): SignInBlockedException => $this->blocked($attempt, $userId),
        );

        $passwordMatches = $this->passwordMatches($attempt->password, $user);

        throw_unless(
            $user instanceof User && $passwordMatches && $this->eligibility->isEligible($user),
            fn (): SignInFailedException => $this->failed($attempt, $userId),
        );

        $this->throttle->recordSuccess($attempt->email);
        $timebox->returnEarly();

        return $user;
    }

    private function passwordMatches(Stringable $password, ?User $user): bool
    {
        $plainPassword = $password->toString();

        if (! $user instanceof User) {
            Hash::check($plainPassword, self::UNKNOWN_EMAIL_PASSWORD_HASH);

            return false;
        }

        return Hash::check($plainPassword, $user->password);
    }

    private function blocked(SignInAttempt $attempt, ?int $userId): SignInBlockedException
    {
        $this->throttle->recordBlockedAttempt($attempt->email, $userId, $attempt->ip);

        return new SignInBlockedException;
    }

    private function failed(SignInAttempt $attempt, ?int $userId): SignInFailedException
    {
        $this->throttle->recordFailure($attempt->email, $userId, $attempt->ip);

        return new SignInFailedException;
    }
}
