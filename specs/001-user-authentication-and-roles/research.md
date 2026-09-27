# Research: User Authentication and Roles

**Feature**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Limits**: [constraints.md](./constraints.md)

Each entry: Decision · Rationale · Alternatives considered. All Technical Context unknowns are resolved here.

## R1. Authentication mechanism for the SPA

- **Decision**: Laravel Sanctum in SPA (stateful cookie) mode. `bootstrap/app.php` enables `$middleware->statefulApi()`; API routes live in `routes/api.php` under `/api/v1` and authenticate with `auth:sanctum`, which resolves the `web` session guard. The SPA fetches `/sanctum/csrf-cookie` once and sends `X-XSRF-TOKEN`. No personal access tokens are issued by this feature.
- **Rationale**: The constitution (Security Requirements) mandates Sanctum for API authentication and CSRF protection. Cookie sessions keep credentials out of JavaScript storage, and session regeneration on sign-in prevents fixation. `php artisan install:api` also publishes the `personal_access_tokens` migration. Keep that table: if it is missing, a request that carries a stray bearer token makes the Sanctum guard query a table that does not exist and return 500.
- **Alternatives**: (a) Plain `web` middleware on `/api/v1` without Sanctum is simpler but breaks the constitution's Sanctum rule. (b) Bearer tokens in `localStorage` expose tokens to XSS, and nothing in the spec needs them.

## R2. Ending sessions on deactivation, company deactivation, and password reset (FR-006, FR-033, FR-036, FR-039)

- **Decision**: Add a `users.session_version` integer. Sign-in stores the current value in the session under a fixed key. On every authenticated request, the `EnsureSessionIsCurrent` middleware asks `Identity\PublicApi\SessionValidity` whether all of these hold: the user is active, the user's company (if any) is active, and the stored version equals the user's current version. If any check fails, it logs out, invalidates the session, and returns 401. User deactivation, password reset, and company deactivation increment `session_version` for the affected users. Role changes need no session change, because the guard reloads the user on every request.
- **Rationale**: This works with every session driver, including the `array` driver that `phpunit.xml` pins for tests. It ends sessions for good, even if the user or company is reactivated before the old session's next request. One mechanism covers all four requirements.
- **Alternatives**: (a) Deleting rows from the `sessions` table depends on the database driver and cannot be tested with the `array` driver. (b) Sanctum/Laravel `AuthenticateSession` covers only password changes. (c) An activity-only check in middleware lets an old session survive a quick deactivate-then-reactivate, which breaks FR-036 and FR-039.

## R3. Password-guessing protection (FR-008, FR-009, SC-008)

- **Decision**: `Identity\Services\SignInThrottleService` stores its state in the cache (default store):
  - **Per account**: The key is the normalized email, so unknown emails behave exactly like real ones. The failure counter has no time window. A successful sign-in resets it. When it reaches the per-account limit, the service writes an account block key with a TTL of the account block period and resets the counter.
  - **Per source**: The key is the client IP. `RateLimiter::hit` counts failures in a fixed window of the per-source period, starting at the first failure. When the count reaches the per-source limit, the service writes a source block key with a TTL of the source block period and clears the counter.
  - Before checking credentials, the service checks for an active account or source block. A blocked attempt returns 429 with one generic message, never checks credentials, and does not add to either counter.
- **Rationale**: This matches `constraints.md` literally: "5 consecutive" has no time window, and the block period starts when the limit is reached. The same behaviour for unknown emails keeps SC-009.
- **Alternatives**: `RateLimiter` alone would make the block end when the first failure's window ends, so the block could last less than the stated block period. A sliding window per source would need a timestamp list, and nothing in the spec requires that precision.

## R4. Indistinguishable sign-in failures (FR-004, SC-009)

- **Decision**: `SignInAction` runs inside `Illuminate\Support\Timebox`, with the same default duration that Laravel's `SessionGuard::attempt` uses. For an unknown email it still runs one `Hash::check` against a fixed dummy hash. Wrong password, unknown email, deactivated user, and deactivated company all throw the same `SignInFailedException`: 422, `errors.email = [__('auth.failed')]`.
- **Rationale**: This removes both the message difference and the timing difference.
- **Alternatives**: `Auth::attempt` in the controller would mix HTTP and business logic, and it has no hook for the company and deactivation checks.

## R5. Security events (FR-011)

- **Decision**: A `sign_in_security_events` table owned by Identity. Columns: `type` (`failed_attempt`, `attempt_while_blocked`, `account_blocked`, `source_blocked`), the normalized `email` as submitted, a nullable `user_id`, `ip_address`, and `created_at`. It never stores a password. No retention period is defined, so rows are kept.
- **Rationale**: The spec treats security events as stored records for security review. Logs rotate, and the constitution forbids writing PII to logs. The table lives in the same system of record as `users`.
- **Alternatives**: `Log::warning` would put emails and IPs in logs, which the constitution forbids.

## R6. Password reset (FR-010, FR-030–FR-033)

- **Decision**: Use Laravel's password broker (`Password::broker()`), which stores hashed tokens in `password_reset_tokens`. `config/auth.php` already sets `passwords.users.expire = 60` and `throttle = 60`, which match `constraints.md`.
  - `RequestPasswordResetAction` sends a reset link only when the email belongs to an eligible account: an active user of an active company, or an active super admin. Every outcome returns the same 200 confirmation, including ineligible, unknown, and throttled.
  - `ResetPasswordAction` rechecks eligibility before calling `Password::reset`. An ineligible user gets the same "link invalid" error as a bad token. A successful reset increments `session_version` (see R2).
  - User deactivation and company deactivation delete the affected users' reset tokens.
  - `User::sendPasswordResetNotification` sends a queued notification whose link points at the SPA route `/reset-password/{token}?email=…`.
- **Rationale**: Reuses framework token hashing, expiry, single use, and throttling without new code.
- **Alternatives**: A custom token table would duplicate what the broker already does.

## R7. Invitations (FR-021–FR-029)

- **Decision**: An `invitations` table stores `company_id`, `email`, `role`, `token_hash` (SHA-256 of a 64-character random token), `expires_at`, `accepted_at`, and `revoked_at`. The state is derived from those columns, in this order: accepted, then revoked, then expired, then pending.
  - **Re-send**: Rotates `token_hash` and sets `expires_at` to now plus the invitation lifetime, so the old link stops working.
  - **Revoke**: Sets `revoked_at`. The row is kept.
  - **Accept**: Inside one transaction, locks the invitation row, rechecks that it is pending and that the email is not already registered, creates the user, and sets `accepted_at`. A unique-index collision is treated as "no longer valid".
  - **Notification**: The invitation email is an on-demand notification, queued with `afterCommit`.
- **Rationale**: Hashed tokens mean a database leak does not expose working links. Deriving state from columns avoids a separate status field that could drift out of sync.
- **Alternatives**: Signed URLs cannot be revoked or rotated without extra state.

## R8. First-admin invitations and the super admin boundary (FR-019, FR-020)

- **Decision**: A company is "awaiting first admin" while it has zero users. The `EnsureCompanyAwaitsFirstAdmin` route middleware calls `Identity\PublicApi\FirstAdminInvitations::isAwaitingFirstAdmin()` and returns 403 once the company has any user. Super-admin invitations always use the `company_admin` role.
- **Rationale**: The first user of a company can only come from a super-admin invitation. So "has zero users" is exactly "no first-admin invitation has been accepted". Putting the check in middleware keeps authorization out of Actions, as the constitution requires (Section I).
- **Alternatives**: A flag column on `companies` would duplicate information that Identity already owns.

## R9. Last-active-admin rule under concurrency (FR-037, SC-007)

- **Decision**: `ChangeCompanyUserRoleAction` and `DeactivateCompanyUserAction` acquire `Cache::lock('identity:company-admins:{companyId}')` in blocking mode, wait for it, and then open a `DB::transaction`. Inside the transaction they recount the company's active admins, excluding the target user, and throw `LastActiveAdminException` (409) if none would remain. The lock TTL and wait time are named constants in the Action.
- **Rationale**: This works the same on SQLite, MySQL, and PostgreSQL. The default `database` cache store and the test `array` store both support atomic locks. Concurrent requests queue and wait (repo rule `atomic-file-uploads`: serialise and wait, no try-lock codes).
- **Alternatives**: `lockForUpdate` does nothing on SQLite, and the default connection is SQLite.

## R10. Module layout and the weak link between modules

- **Decision**: There are two business modules.
  - **Identity**: users, roles, invitations, sign-in, passwords, security events, and session validity.
  - **Companies**: the company record and its active/deactivated state.
  - Cross-module calls use only `PublicApi` interfaces, readonly DTOs, and events: `Companies\PublicApi\CompanyDirectory`, the `Companies\PublicApi\CompanyDeactivated` event, and `Identity\PublicApi\FirstAdminInvitations`.
  - The authenticated principal crosses into `app/Http` as `Identity\PublicApi\Actor`, an interface that extends `Authenticatable` and is implemented by the User model. Http code never imports the User model.
  - Each module's bindings, listeners, and console commands are registered by a module service provider listed in `bootstrap/providers.php`. This keeps `app/Providers` from importing module internals.
- **Rationale**: This follows constitution Section I.c without recorded exceptions. Companies is a separate business area; the raw backlog item `006-companies` extends it.
- **Alternatives**: A single module would also hold future company settings, which makes it grow in the wrong direction. Route model binding of module models in controllers would import internals into `app/Http`.

## R11. DTOs (Spatie Laravel Data) and Actions

- **Decision**: Input DTOs live in `App\Modules\{Identity,Companies}\Data` as `Spatie\LaravelData\Data` objects (plan §Dependencies: `spatie/laravel-data` ^4). Form Requests validate input and `toDto()` builds those Data objects with Laravel typed request helpers (`string()`, `integer()`, and the like), per constitution I.a and `.cursor/skills/spatie-data/SKILL.md`. Cross-module `PublicApi` view types stay plain readonly classes where the data model defines them. Actions remain plain invokable classes; this feature does not add `spatie/laravel-queueable-action`.
- **Rationale**: Constitution I.a requires Spatie Laravel Data for Form Request `toDto()` values. Framework validation alone does not provide the typed Data mapping workflow Actions consume.
- **Alternatives**: Plain `final readonly` DTOs (as in the `Health` module) would violate I.a for this feature's HTTP boundary.

## R12. Frontend routing, i18n, HTTP client

- **Decision**:
  - Add `vue-router@^4`. Email links need deep links (`/invitation/:token`, `/reset-password/:token`), and pages need auth and role guards.
  - For i18n, `resources/js/utils/i18n.ts` provides a small `t(key, params)` over `resources/js/locales/{en,ru}.json`. The locale comes from `<html lang>`. No i18n package.
  - The HTTP client is a `fetch` wrapper in `resources/js/api/client.ts`. It sends `credentials: 'same-origin'`, reads the `XSRF-TOKEN` cookie, and normalizes 401/403/404/409/410/422/429 into typed errors. No axios.
  - Current-user state lives in a composable, `resources/js/composables/useCurrentUser.ts`. No Pinia.
- **Rationale**: Only one new npm dependency, and that is the one a multi-route SPA with deep links actually needs.
- **Alternatives**: Hand-written hash routing would reimplement vue-router. vue-i18n and axios would each add a dependency that a small helper replaces.

## R13. Tests with Sanctum stateful requests

- **Decision**: Feature tests send a `Referer` header with a Sanctum stateful domain (the `APP_URL` host, via a helper in `tests/Support/Identity/`), so that session middleware runs. Tests use `actingAs($user, 'web')`. Mail and notifications use `Notification::fake()`. Throttle and lock state use the `array` cache from `phpunit.xml`. Time uses `Carbon::setTestNow`.
- **Rationale**: Without a stateful Referer, Sanctum skips the session, and `EnsureSessionIsCurrent` cannot read the stored version.
- **Alternatives**: Bypassing the middleware in tests would leave FR-006 untested.

## R14. Email case-insensitivity (FR-007)

- **Decision**: Emails are lowercased (`Str::lower`, multibyte) wherever they enter: Form Request `toDto()` and the console command. They are stored lowercased under a unique index on `users.email`. Lookups use the lowercased value.
- **Rationale**: Uniqueness and comparisons are then exact at the database level on every engine.
- **Alternatives**: Case-insensitive collations differ between SQLite, MySQL, and PostgreSQL.

## R15. General API rate limit (constitution Security Requirements)

- **Decision**: A named `api` limiter in `AppServiceProvider` keyed by user id, or IP when there is no user, with the limit in `constraints.md`. Applied to every `/api/v1` route. The sign-in and reset-specific limits in R3 and R6 apply on top.
- **Rationale**: The constitution requires rate limiting on API endpoints. The value is the per-minute limit from Laravel's documentation example.
- **Alternatives**: No general limit would break the constitution.
