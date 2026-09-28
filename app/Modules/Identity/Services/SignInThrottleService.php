<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Data\SignInSecurityEventType;
use App\Modules\Identity\Models\SignInSecurityEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class SignInThrottleService
{
    public const int ACCOUNT_FAILURE_LIMIT = 5;

    public const int ACCOUNT_BLOCK_MINUTES = 15;

    public const int SOURCE_FAILURE_LIMIT = 20;

    public const int SOURCE_WINDOW_MINUTES = 15;

    public const int SOURCE_BLOCK_MINUTES = 15;

    public const string AUTHENTICATION_FAILURE_EVENT = 'authentication_failure';

    private const string ACCOUNT_FAILURES_PREFIX = 'identity:sign-in:account-failures:';

    private const string ACCOUNT_BLOCK_PREFIX = 'identity:sign-in:account-block:';

    private const string SOURCE_FAILURES_PREFIX = 'identity:sign-in:source-failures:';

    private const string SOURCE_BLOCK_PREFIX = 'identity:sign-in:source-block:';

    private const int SECONDS_PER_MINUTE = 60;

    public function isBlocked(Stringable|string $email, string $ip): bool
    {
        $email = $this->normalizedEmail($email);

        return Cache::has($this->accountBlockKey($email)) || Cache::has($this->sourceBlockKey($ip));
    }

    public function recordBlockedAttempt(Stringable|string $email, ?int $userId, string $ip): void
    {
        $this->writeEvent(
            SignInSecurityEventType::AttemptWhileBlocked,
            $this->normalizedEmail($email),
            $userId,
            $ip,
        );
    }

    public function recordFailure(Stringable|string $email, ?int $userId, string $ip): void
    {
        $email = $this->normalizedEmail($email);

        $this->writeEvent(SignInSecurityEventType::FailedAttempt, $email, $userId, $ip);
        $this->recordAccountFailure($email, $userId, $ip);
        $this->recordSourceFailure($email, $userId, $ip);
    }

    public function recordSuccess(Stringable|string $email): void
    {
        Cache::forget($this->accountFailuresKey($this->normalizedEmail($email)));
    }

    private function recordAccountFailure(string $email, ?int $userId, string $ip): void
    {
        $failuresKey = $this->accountFailuresKey($email);
        $count = (int) Cache::get($failuresKey, 0) + 1;

        if ($count < self::ACCOUNT_FAILURE_LIMIT) {
            Cache::forever($failuresKey, $count);

            return;
        }

        Cache::put(
            $this->accountBlockKey($email),
            true,
            self::ACCOUNT_BLOCK_MINUTES * self::SECONDS_PER_MINUTE,
        );
        Cache::forget($failuresKey);
        $this->writeEvent(SignInSecurityEventType::AccountBlocked, $email, $userId, $ip);
    }

    private function recordSourceFailure(string $email, ?int $userId, string $ip): void
    {
        $hits = RateLimiter::hit(
            $this->sourceFailuresKey($ip),
            self::SOURCE_WINDOW_MINUTES * self::SECONDS_PER_MINUTE,
        );

        if ($hits < self::SOURCE_FAILURE_LIMIT) {
            return;
        }

        Cache::put(
            $this->sourceBlockKey($ip),
            true,
            self::SOURCE_BLOCK_MINUTES * self::SECONDS_PER_MINUTE,
        );
        RateLimiter::clear($this->sourceFailuresKey($ip));
        $this->writeEvent(SignInSecurityEventType::SourceBlocked, $email, $userId, $ip);
    }

    private function writeEvent(SignInSecurityEventType $type, string $email, ?int $userId, string $ip): void
    {
        SignInSecurityEvent::query()->create([
            'type' => $type,
            'email' => $email,
            'user_id' => $userId,
            'ip_address' => $ip,
        ]);

        Log::warning('Authentication failed', [
            'event' => self::AUTHENTICATION_FAILURE_EVENT,
            'reason' => $type->value,
        ]);
    }

    private function normalizedEmail(Stringable|string $email): string
    {
        return ($email instanceof Stringable ? $email->lower() : Str::of($email)->lower())->toString();
    }

    private function accountFailuresKey(string $email): string
    {
        return self::ACCOUNT_FAILURES_PREFIX.hash('sha1', $email);
    }

    private function accountBlockKey(string $email): string
    {
        return self::ACCOUNT_BLOCK_PREFIX.hash('sha1', $email);
    }

    private function sourceFailuresKey(string $ip): string
    {
        return self::SOURCE_FAILURES_PREFIX.$ip;
    }

    private function sourceBlockKey(string $ip): string
    {
        return self::SOURCE_BLOCK_PREFIX.$ip;
    }
}
