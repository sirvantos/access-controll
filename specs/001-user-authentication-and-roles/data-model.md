# Data Model: User Authentication and Roles

**Feature**: [spec.md](./spec.md) · **Limits**: [constraints.md](./constraints.md) · **Decisions**: [research.md](./research.md)

Every model defines `casts()` for every column. Emails are stored lowercased (R14). No row in `users`, `companies`, or `invitations` is ever deleted.

## Module ownership

| Table | Owning module | Model |
|-------|---------------|-------|
| `companies` | Companies | `App\Modules\Companies\Models\Company` |
| `users` | Identity | `App\Modules\Identity\Models\User` (moved from `App\Models\User`; `config/auth.php` provider updated) |
| `invitations` | Identity | `App\Modules\Identity\Models\Invitation` |
| `sign_in_security_events` | Identity | `App\Modules\Identity\Models\SignInSecurityEvent` |
| `password_reset_tokens` | Identity (framework broker table, existing) | none |
| `sessions` | framework (existing) | none |
| `personal_access_tokens` | framework (Sanctum, R1) | none; unused |

`users.company_id` and `invitations.company_id` have schema foreign keys to `companies.id`. Code does not cross modules: there is no Eloquent relation from Identity models to `Company`, and no Identity query names `companies`. Identity reads company state only through `Companies\PublicApi\CompanyDirectory`.

## Company (Companies)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string(255) | required, 1 to 255 characters (constraints.md) |
| `deactivated_at` | timestamp, nullable | null means active |
| `created_at`, `updated_at` | timestamps | |

State: `active` (when `deactivated_at` is null) ⇄ `deactivated`. Both transitions are done by a super admin and are idempotent. Deactivating dispatches `Companies\PublicApi\CompanyDeactivated(companyId)` synchronously, inside the same transaction.

## User (Identity)

A new migration changes the existing `users` table: it drops `name` and adds the columns below. `email_verified_at` and `remember_token` stay unchanged; this feature does not use them.

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `email` | string(255), unique | lowercased; unique across all users, including super admins (FR-007) |
| `password` | string | `hashed` cast; never serialized (FR-013) |
| `role` | string(32) | cast to `Identity\PublicApi\Role` enum: `super_admin`, `company_admin`, `viewer` |
| `company_id` | FK → `companies.id`, nullable | null exactly when `role = super_admin` (FR-015); enforced in Actions |
| `deactivated_at` | timestamp, nullable | null means active |
| `session_version` | unsigned int, default 1 | incremented to end every session (R2) |
| `email_verified_at` | timestamp, nullable | existing, unused |
| `remember_token` | string, nullable | existing, unused |
| `created_at`, `updated_at` | timestamps | |

Index: (`company_id`, `role`, `deactivated_at`), for the active-admin count.

Implements `Identity\PublicApi\Actor`: `actorId()`, `actorRole()`, `actorCompanyId()`, `sessionVersion()`.

State transitions:
- *(invitation accepted)* → `active`.
- `active` → `deactivated`: done by a company admin of the same company. It increments `session_version`, deletes the user's reset token, and is refused if the user is the last active admin (FR-037).
- `deactivated` → `active`: done by a company admin of the same company. The password and role are kept.
- Role `company_admin` ⇄ `viewer`: done by a company admin of the same company. Demoting the last active admin is refused.
- A super admin is created only by the console command (FR-040). No transition leads to or from `super_admin`.

## Invitation (Identity)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `company_id` | FK → `companies.id` | required |
| `email` | string(255), indexed | lowercased; must not belong to an existing user at invite, re-send, and accept time (FR-026) |
| `role` | string(32) | `company_admin` or `viewer` only (FR-029) |
| `token_hash` | char(64), unique | SHA-256 of the emailed token |
| `expires_at` | timestamp | sending time plus the invitation lifetime (constraints.md) |
| `accepted_at` | timestamp, nullable | |
| `revoked_at` | timestamp, nullable | |
| `created_at`, `updated_at` | timestamps | |

The state is derived by a model method, never stored. The first matching rule wins: `accepted` if `accepted_at` is set; `revoked` if `revoked_at` is set; `expired` if `expires_at` is at or before now; otherwise `pending`.

Transitions (only from `pending`):
- **re-send**: a new `token_hash` and a new `expires_at`; the old link stops working.
- **revoke**: sets `revoked_at`.
- **accept**: creates the User with the invitation's `company_id`, `role`, and `email` plus the chosen password, then sets `accepted_at`. This runs in one transaction with the invitation row locked.

Several pending invitations for the same email may exist, in one company or in several. The spec forbids inviting existing users only. The first acceptance wins; after that, the others fail acceptance because the email is taken.

## SignInSecurityEvent (Identity)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `type` | string(32) | enum `SignInSecurityEventType`: `failed_attempt`, `attempt_while_blocked`, `account_blocked`, `source_blocked` |
| `email` | string(255) | normalized submitted email, possibly unknown |
| `user_id` | FK → `users.id`, nullable | set when the email matches a user |
| `ip_address` | string(45) | the request source |
| `created_at` | timestamp | no `updated_at` |

It never contains a password (FR-011, FR-013). Rows are append-only and there is no retention policy (not specified).

## Password reset token (framework)

The existing `password_reset_tokens` table (`email` PK, hashed `token`, `created_at`) is managed by the broker. The lifetime and throttle come from `config/auth.php` (`expire = 60`, `throttle = 60`), which match constraints.md. Rows are deleted when a reset succeeds, when the user is deactivated, and for all of a company's users when the company is deactivated.

## Throttle state (cache, not a table)

| Key | Content | TTL |
|-----|---------|-----|
| `identity:sign-in:account-failures:{sha1(email)}` | consecutive failure count | none (cleared on success or when a block starts) |
| `identity:sign-in:account-block:{sha1(email)}` | block marker | account block period |
| `identity:sign-in:source-failures:{ip}` | `RateLimiter` counter | source window |
| `identity:sign-in:source-block:{ip}` | block marker | source block period |
| `identity:company-admins:{companyId}` | atomic lock (R9) | lock TTL constant |

## PublicApi types

| Type | Kind | Members |
|------|------|---------|
| `Identity\PublicApi\Role` | backed enum (value type) | `SuperAdmin = 'super_admin'`, `CompanyAdmin = 'company_admin'`, `Viewer = 'viewer'`; `assignable(): list<self>` returns the last two |
| `Identity\PublicApi\Actor` | interface extends `Authenticatable` | `actorId(): int`, `actorRole(): Role`, `actorCompanyId(): ?int`, `sessionVersion(): int` |
| `Identity\PublicApi\SessionValidity` | interface | `isCurrent(Actor $actor, ?int $storedSessionVersion): bool` |
| `Identity\PublicApi\FirstAdminInvitations` | interface | `invite(int $companyId, string $email): void`; `isAwaitingFirstAdmin(int $companyId): bool`; `companyIdsAwaitingFirstAdmin(list<int> $companyIds): list<int>` |
| `Identity\PublicApi\CompanyUserView` | readonly DTO | `id`, `email`, `role`, `isActive` |
| `Identity\PublicApi\PendingInvitationView` | readonly DTO | `id`, `email`, `role`, `expiresAt` |
| `Identity\PublicApi\InvitationPreview` | readonly DTO | `email`, `role`, `expiresAt` |
| `Companies\PublicApi\CompanyDirectory` | interface | `exists(int $companyId): bool`; `isActive(int $companyId): bool` |
| `Companies\PublicApi\CompanySummary` | readonly DTO | `id`, `name`, `isActive`, `awaitingFirstAdmin`, `createdAt` |
| `Companies\PublicApi\CompanyDeactivated` | event | `companyId` |

## Domain exceptions (`app/Exceptions`, all `ShouldntReport`)

| Exception | HTTP | Body |
|-----------|------|------|
| `SignInFailedException` | 422 | `errors.email = [auth.failed]` |
| `SignInBlockedException` | 429 | `errors.email = [auth.blocked]` |
| `EmailAlreadyRegisteredException` | 422 | `errors.email = [identity.email_already_registered]` (or `errors.first_admin_email` on company creation) |
| `LastActiveAdminException` | 409 | `error_code = last_active_admin` |
| `InvitationNotPendingException` | 409 | `error_code = invitation_not_pending` |
| `InvitationNoLongerValidException` | 410 | `error_code = invitation_invalid` |
| `PasswordResetLinkInvalidException` | 422 | `errors.token = [passwords.token]` |

The message keys live in `lang/en/*.php` and `lang/ru/*.php` (parity rule).
