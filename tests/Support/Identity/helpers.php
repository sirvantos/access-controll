<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Identity\Services\SignInThrottleService;
use App\Modules\Tenancy\Data\TenancyLimits;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Support\CompanyContextStore;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Identity\MessageLoggedBucket;
use Tests\TestCase;

const SAMPLE_INVITATION_TOKEN = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

const SAMPLE_INVITATION_TOKEN_ALT = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

const SAMPLE_PASSWORD = 'password';

/**
 * @return array<string, string>
 */
function statefulHeaders(): array
{
    $appUrl = (string) config('app.url');
    $host = parse_url($appUrl, PHP_URL_HOST);
    $port = parse_url($appUrl, PHP_URL_PORT);
    $authority = is_string($host) ? $host : 'localhost';

    if (is_int($port)) {
        $authority .= ':'.$port;
    }

    return [
        'Referer' => 'http://'.$authority,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function acmeCompany(array $overrides = []): Company
{
    return Company::factory()->create(array_replace([
        'name' => 'Acme',
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function globexCompany(array $overrides = []): Company
{
    return Company::factory()->create(array_replace([
        'name' => 'Globex',
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function ownerSuperAdmin(array $overrides = []): User
{
    return User::factory()->superAdmin()->create(array_replace([
        'email' => 'owner@example.com',
        'password' => SAMPLE_PASSWORD,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function acmeAdmin(array $overrides = []): User
{
    return identityCompanyUser(acmeCompany(...), 'admin@acme.test', Role::CompanyAdmin, $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function acmeViewer(array $overrides = []): User
{
    return identityCompanyUser(acmeCompany(...), 'viewer@acme.test', Role::Viewer, $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function globexAdmin(array $overrides = []): User
{
    return identityCompanyUser(globexCompany(...), 'admin@globex.test', Role::CompanyAdmin, $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function globexViewer(array $overrides = []): User
{
    return identityCompanyUser(globexCompany(...), 'viewer@globex.test', Role::Viewer, $overrides);
}

function captureLogEvents(): MessageLoggedBucket
{
    $bucket = new MessageLoggedBucket;

    Log::listen(fn (MessageLogged $event) => $bucket->events[] = $event);

    return $bucket;
}

function expectNothingLogged(MessageLoggedBucket $bucket): void
{
    expect($bucket->events)->toBeEmpty();
}

/**
 * @param  list<string>  $secrets
 */
function expectAuthenticationFailureLogs(MessageLoggedBucket $bucket, array $secrets): void
{
    $failures = array_values(array_filter(
        $bucket->events,
        fn (MessageLogged $event): bool => ($event->context['event'] ?? null) === SignInThrottleService::AUTHENTICATION_FAILURE_EVENT,
    ));

    expect($failures)->not->toBeEmpty();

    $reported = array_values(array_filter(
        $bucket->events,
        fn (MessageLogged $event): bool => in_array($event->level, ['emergency', 'alert', 'critical', 'error'], true),
    ));

    expect($reported)->toBeEmpty();

    foreach ($failures as $failure) {
        $encoded = (string) json_encode($failure->context, JSON_THROW_ON_ERROR);

        expect($failure->context)->not->toHaveKey('email')
            ->and($failure->context)->not->toHaveKey('password')
            ->and($failure->context)->not->toHaveKey('token')
            ->and($failure->message)->not->toContain('email');

        foreach ($secrets as $secret) {
            expect($encoded)->not->toContain($secret)
                ->and($failure->message)->not->toContain($secret);
        }
    }
}

/**
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function withoutCompanyIsolation(Closure $callback): mixed
{
    return app(CompanyContext::class)->withoutIsolation($callback);
}

/**
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function withCompanyContext(int $companyId, Closure $callback): mixed
{
    return app(CompanyContext::class)->run($companyId, $callback);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function persistUser(User $user, array $attributes): void
{
    $save = function () use ($user, $attributes): void {
        $user->forceFill($attributes)->save();
    };

    if ($user->company_id === null) {
        withoutCompanyIsolation($save);

        return;
    }

    withCompanyContext($user->company_id, $save);
}

function freshUser(User $user): ?User
{
    if ($user->company_id === null) {
        return withoutCompanyIsolation(fn (): ?User => $user->fresh());
    }

    return withCompanyContext($user->company_id, fn (): ?User => $user->fresh());
}

function refreshUser(User $user): User
{
    if ($user->company_id === null) {
        return withoutCompanyIsolation(fn (): User => $user->refresh());
    }

    return withCompanyContext($user->company_id, fn (): User => $user->refresh());
}

/**
 * @param  array<string, mixed>  $attributes
 */
function persistCompany(Company $company, array $attributes): void
{
    withCompanyContext($company->id, function () use ($company, $attributes): void {
        $company->forceFill($attributes)->save();
    });
}

function freshCompany(Company $company): ?Company
{
    return withCompanyContext($company->id, fn (): ?Company => $company->fresh());
}

function signedInAs(Actor $user): TestCase
{
    $store = app(CompanyContextStore::class);
    $companyId = $user->actorCompanyId();

    if ($companyId !== null) {
        $store->bindCompany($companyId);
    }

    $store->setActorId($user->actorId());
    $store->setActorRole($user->actorRole()->value);

    /** @var TestCase $case */
    $case = test();

    return $case
        ->actingAs($user, 'web')
        ->withSession([EnsureSessionIsCurrent::SESSION_KEY => $user->sessionVersion()])
        ->withHeaders(statefulHeaders());
}

/**
 * @return array<string, string>
 */
function companyContextHeaders(int $companyId): array
{
    return array_merge(statefulHeaders(), [
        TenancyLimits::COMPANY_CONTEXT_HEADER => (string) $companyId,
    ]);
}

function signedInWithSelectedCompany(Actor $user, int $companyId): TestCase
{
    $case = signedInAs($user);
    $case->postJson('/api/v1/admin/selected-company', ['company_id' => $companyId])->assertOk();

    return $case->withHeaders(companyContextHeaders($companyId));
}

/**
 * @param  callable(array<string, mixed>): Company  $company
 * @param  array<string, mixed>  $overrides
 */
function identityCompanyUser(callable $company, string $email, Role $role, array $overrides = []): User
{
    $companyId = $overrides['company_id'] ?? $company()->id;
    throw_unless(is_int($companyId), InvalidArgumentException::class, 'company_id override must be an integer.');

    $factory = User::factory();
    $factory = $role === Role::CompanyAdmin
        ? $factory->companyAdmin($companyId)
        : $factory->viewer($companyId);

    return $factory->create(array_replace([
        'email' => $email,
        'password' => SAMPLE_PASSWORD,
        'company_id' => $companyId,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function invitationFor(Company|int $company, string $plainToken = SAMPLE_INVITATION_TOKEN, array $overrides = []): Invitation
{
    $companyId = $company instanceof Company ? $company->id : $company;

    return Invitation::factory()->create(array_replace([
        'company_id' => $companyId,
        'email' => 'invitee@acme.test',
        'role' => Role::CompanyAdmin,
        'expires_at' => '2030-01-15 12:00:00',
        'accepted_at' => null,
        'revoked_at' => null,
    ], $overrides, [
        'company_id' => $companyId,
        'token_hash' => hash('sha256', $plainToken),
    ]));
}

/**
 * @return list<InvitationNotification>
 */
function sentInvitationNotifications(): array
{
    $sent = [];

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        function (InvitationNotification $notification) use (&$sent): bool {
            $sent[] = $notification;

            return true;
        },
    );

    return $sent;
}
