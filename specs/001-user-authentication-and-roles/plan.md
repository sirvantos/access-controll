# Implementation Plan: User Authentication and Roles

**Branch**: `001-user-authentication-and-roles` | **Date**: 2026-09-27 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/001-user-authentication-and-roles/spec.md` (clarified 2026-09-27; Q1 = A). The limits are in [constraints.md](./constraints.md).

## Summary

The plan adds email and password authentication with three fixed roles to the Laravel 13 and Vue 3 skeleton.

- **Authentication**: Sanctum SPA cookie sessions (R1). A `session_version` column ends every session when a user is deactivated, when their company is deactivated, or when they reset their password (R2).
- **Sign-in protection**: The sign-in path has per-account and per-source blocking backed by the cache, a timeboxed and uniform failure response, and a `sign_in_security_events` table (R3–R5).
- **Invitations and recovery**: Invitations use hashed, rotating tokens (R7). Password recovery uses the framework broker with an eligibility gate (R6).
- **Access control**: Route middleware enforces roles (`role:`). Company-scoped Actions resolve ids only inside the signed-in user's company, so another company's records return 404. A super admin reaches a company only through the first-admin invitation routes, and only while the company has no users (R8).
- **Last active admin**: A cache lock with a transaction enforces the rule under concurrency (R9).
- **Modules**: `Identity` and `Companies` are linked only through `PublicApi` (R10).
- **Frontend**: The SPA adds vue-router pages for sign-in, recovery, invitation acceptance, company users, and companies.

## Technical Context

**Language/Version**: PHP 8.4 (`declare(strict_types=1)`), TypeScript 5.9, Vue 3.5

**Primary Dependencies**: laravel/framework ^13.17; **new**: `laravel/sanctum` ^4 (R1), `spatie/laravel-data` ^4 (constitution I.a), and `vue-router` ^4 (R12). Existing: Pest 4, Larastan 3 (level 8), Pint, deptrac, Vite 8, Vitest 5, Tailwind 4.

**Storage**: Relational database through Eloquent, with nothing specific to one engine. `.env.example` uses SQLite, and tests use in-memory SQLite. Sessions use the `database` driver (`array` in tests). The cache and locks use the default `database` store (`array` in tests).

**Testing**: Pest feature tests under `tests/Feature/Modules/{Identity,Companies}`; unit tests under `tests/Unit/Modules/...` only for branches that HTTP cannot reach; Vitest specs in `resources/js/**/__tests__`.

**Target Platform**: A Linux server running Laravel (served by Herd locally) and an evergreen-browser SPA.

**Project Type**: Web service with a same-origin SPA (a single Laravel project).

**Performance Goals**: No throughput target in the spec. Sign-in latency is set on purpose by the timebox (R4). SC-004 (under 2 min) and SC-005 (under 3 min) are user-flow targets that the synchronous API meets, because only email delivery is queued.

**Constraints**: All limits come from [constraints.md](./constraints.md). Rights take effect on the next request (FR-006). Every sign-in failure looks the same (SC-009). Constitution VI.a requires each failed sign-in and each sign-in block to be logged as a structured authentication-failure event that omits passwords, email, tokens, and other PII; the `sign_in_security_events` table still holds FR-011 review data (including normalized email). The code must be safe to run under Octane: no request state in statics.

**Scale/Scope**: Several companies, each with a small number of CRM users. 7 user stories, 40 functional requirements, 23 API routes, 1 console command, and 7 SPA pages.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | How this plan complies | Status |
|-----------|------------------------|--------|
| I Layer separation | Controllers are invokable: they call one Action and return a `JsonResource`. Actions and Services are pure, with no Request, Session, or Gate. Session work (sign-in regeneration, storing `session_version`, sign-out) stays in controllers and middleware. Authorization lives in route middleware (`role:`, `awaiting-first-admin`). Actions enforce only business rules (last admin, pending state, email uniqueness) and tenant-scoped lookups (404). | Pass |
| I.c Weak links | Identity and Companies talk only through `PublicApi` interfaces, readonly DTOs, the `Role` value enum, and the `CompanyDeactivated` event (data-model §PublicApi types). There is no cross-module Eloquent relation and no query on another module's table. `app/Http` imports only module Actions (allowed for route owners) and `PublicApi` types. The authenticated principal is `Identity\PublicApi\Actor`. Bindings, listeners, and commands are registered by module service providers, so `app/Providers` imports no module internals. | Pass, no exceptions needed |
| I.a SOLID / reuse | There is one Action per use case. The shared capabilities are `SignInThrottleService`, `AccountEligibilityService`, `InvitationTokenService`, and `AdminSeatGuardService`. `PasswordRules` and `EmailRules` in `App\Support\Validation` are shared by Form Requests and the console command. `CompanyUserResource` and `PendingInvitationResource` are reused by the admin and super-admin routes. | Pass |
| I.a DTOs via Spatie Data | Form Request `toDto()` returns Spatie Laravel Data objects in `App\Modules\{Module}\Data`, built with Laravel typed request helpers (`string()`, `integer()`, and the like) per `.cursor/skills/spatie-data/SKILL.md`. Cross-module `PublicApi` shapes stay plain readonly types where the data model defines them. | Pass |
| II Strict typing / no magic values | Enums: `Role` and `SignInSecurityEventType`. Limits are typed class constants named after the business field, aligned with constraints.md. Larastan level 8 with no ignores. | Pass |
| III Test-first | Every acceptance scenario is mapped to a Pest test (quickstart §Scenario map). Endpoint tests run the real Controller → Form Request → Action → Resource path. Notifications, time, and cache are faked. Every domain exception has a silence proof. | Pass |
| IV API standards | Routes are under `/api/v1`. Every response goes through a `JsonResource`; `OkResource` is the only unwrapped one. Pagination happens in Actions. Payloads are `snake_case`. Error bodies follow contracts/http-api.md. | Pass |
| VI Observability | `public/swagger.yaml` is created from contracts/http-api.md. Each failed sign-in and each sign-in block emits a structured authentication-failure log entry (type/reason codes only; no password, email, token, or other PII). `sign_in_security_events` remains the FR-011 security-review store (R5). | Pass |
| Security | Sanctum SPA with CSRF; session regeneration on sign-in and invalidation on sign-out; hashed invitation and reset tokens; `throttle:api` on every route plus the specific sign-in and reset limits; emails lowercased and unique. | Pass |
| Prohibitions | Three new dependencies, all named and justified below. No change to existing public routes (`/`, `/health`, `/up` stay). No lowered gates. No TODO or placeholder bodies. | Pass |

**Post-design re-check (after Phase 1)**: The same result. The data model and contracts add no cross-module relation or table access, and no route outside the spec.

## Dependencies

| Package | Why existing dependencies cannot do the job |
|---------|---------------------------------------------|
| `laravel/sanctum` ^4 (Composer) | The constitution (Security Requirements) requires Sanctum for API authentication. `statefulApi()` gives the `/api/v1` routes session cookies and CSRF, which the framework does not provide for the `api` group. Install it with `php artisan install:api`. That creates `routes/api.php`, `config/sanctum.php`, and the `personal_access_tokens` migration, which is kept on purpose (R1). |
| `spatie/laravel-data` ^4 (Composer) | Constitution I.a requires Form Request `toDto()` values to be Spatie Laravel Data objects. `laravel/framework` validation and plain PHP classes do not provide the typed Data mapping, casting, and skill workflow mandated for DTOs between HTTP and Actions. |
| `vue-router` ^4 (npm) | Email links need deep links (`/invitation/:token`, `/reset-password/:token`), and there are 7 pages with auth and role guards. `vue` alone has no router, and writing one by hand would duplicate this package. |

No other package is added. i18n, the HTTP client, and state use a small `t()` helper, `fetch`, and a composable (R12).

## Module boundary exceptions

None. Every cross-module call in this plan uses `App\Modules\{Other}\PublicApi\*` (R10). Schema foreign keys from `users.company_id` and `invitations.company_id` to `companies.id` live in `database/migrations` and are not module code.

## Project Structure

### Documentation (this feature)

```text
specs/001-user-authentication-and-roles/
├── spec.md
├── constraints.md       # boundary values (source of truth)
├── plan.md              # this file
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   ├── http-api.md      # REST contract, mirrored into public/swagger.yaml
│   ├── console.md       # identity:create-super-admin
│   └── spa-routes.md    # SPA pages, guards
├── checklists/requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Exceptions/                          # domain exceptions (ShouldntReport + render), data-model §Domain exceptions
├── Http/
│   ├── Controllers/Auth/                # SignIn, SignOut, ForgotPassword, ResetPassword, ShowCurrentUser
│   ├── Controllers/Invitations/         # ShowInvitation, AcceptInvitation
│   ├── Controllers/Company/             # ListCompanyUsers, ChangeCompanyUserRole, Deactivate/ReactivateCompanyUser,
│   │                                    # ListCompanyInvitations, InviteCompanyUser, Resend/RevokeCompanyInvitation
│   ├── Controllers/Admin/               # ListCompanies, CreateCompany, Deactivate/ReactivateCompany,
│   │                                    # List/Invite/Resend/RevokeFirstAdminInvitation
│   ├── Middleware/                      # EnsureSessionIsCurrent, EnsureUserHasRole, EnsureCompanyAwaitsFirstAdmin
│   ├── Requests/                        # SignInRequest, EmailRequest (forgot password), ResetPasswordRequest,
│   │                                    # AcceptInvitationRequest, InviteUserRequest, ChangeRoleRequest,
│   │                                    # CreateCompanyRequest, InviteFirstAdminRequest
│   └── Resources/                       # CurrentUser, CompanyUser, PendingInvitation, InvitationPreview, Company, OkResource
├── Modules/
│   ├── Identity/
│   │   ├── IdentityServiceProvider.php  # PublicApi bindings, CompanyDeactivated listener, console command
│   │   ├── Actions/                     # SignIn, RequestPasswordReset, ResetPassword, CreateSuperAdmin,
│   │   │                                # InviteCompanyUser, InviteFirstAdmin, ResendInvitation, RevokeInvitation,
│   │   │                                # ShowInvitation, AcceptInvitation, ListCompanyUsers, ListPendingInvitations,
│   │   │                                # ChangeCompanyUserRole, DeactivateCompanyUser, ReactivateCompanyUser,
│   │   │                                # EndCompanySessions (listener target)
│   │   ├── Services/                    # SignInThrottle, AccountEligibility (implements SessionValidity),
│   │   │                                # InvitationToken, AdminSeatGuard, FirstAdminInvitations (implements PublicApi)
│   │   ├── Models/                      # User, Invitation, SignInSecurityEvent
│   │   ├── Notifications/               # InvitationNotification, ResetPasswordNotification
│   │   ├── Console/                     # CreateSuperAdminCommand
│   │   ├── Data/                        # Spatie Laravel Data DTOs (SignInAttempt, InviteUserData, …)
│   │   └── PublicApi/                   # Role, Actor, SessionValidity, FirstAdminInvitations,
│   │                                    # CompanyUserView, PendingInvitationView, InvitationPreview
│   └── Companies/
│       ├── CompaniesServiceProvider.php # binds CompanyDirectory
│       ├── Actions/                     # CreateCompany, ListCompanies, DeactivateCompany, ReactivateCompany
│       ├── Services/                    # CompanyDirectoryService (implements PublicApi)
│       ├── Models/                      # Company
│       ├── Data/                        # Spatie Laravel Data (CreateCompanyData)
│       └── PublicApi/                   # CompanyDirectory, CompanySummary, CompanyDeactivated
├── Support/Validation/                  # PasswordRules, EmailRules
└── Providers/AppServiceProvider.php     # `api` rate limiter only
bootstrap/app.php                        # api routes, statefulApi(), middleware aliases
bootstrap/providers.php                  # + IdentityServiceProvider, CompaniesServiceProvider
config/auth.php                          # provider model → App\Modules\Identity\Models\User
database/migrations/                     # create companies; alter users; create invitations; create sign_in_security_events
database/factories/                      # UserFactory (moved model, states: superAdmin, companyAdmin, viewer, deactivated),
                                         # CompanyFactory (deactivated), InvitationFactory (expired, revoked, accepted)
lang/{en,ru}/                            # auth.php, passwords.php, identity.php, validation.php
routes/api.php                           # contracts/http-api.md
routes/web.php                           # SPA catch-all (keeps / and /health)
public/swagger.yaml                      # OpenAPI mirror of contracts/http-api.md
resources/js/
├── router/index.ts                      # contracts/spa-routes.md
├── api/                                 # client.ts, auth.ts, invitations.ts, companyUsers.ts, adminCompanies.ts, types.ts
├── composables/useCurrentUser.ts
├── utils/i18n.ts
├── locales/{en,ru}.json
├── pages/                               # SignIn, ForgotPassword, ResetPassword, AcceptInvitation, Home, CompanyUsers, Companies
├── components/                          # AppHeader and shared form/list pieces
└── **/__tests__/*.spec.ts
tests/
├── Support/Identity/                    # stateful Referer helper, user/company sample helpers (fixed emails)
├── Feature/Modules/Identity/            # quickstart §Scenario map
├── Feature/Modules/Companies/
└── Unit/Modules/Identity/               # throttle windows, token rotation branches not reachable via one call
```

**Structure Decision**: A single Laravel project. Business logic lives in `app/Modules/Identity` and `app/Modules/Companies`, following the layout in the constitution's Conventions. HTTP delivery lives in `app/Http`. The SPA lives in `resources/js`. `app/Models/User.php` moves to the Identity module, and the `app/Models` directory is removed once it is empty.

## Implementation notes for tasks

- **Session version**: The session key is a public constant on `EnsureSessionIsCurrent`. The sign-in controller stores `Actor::sessionVersion()` under that key after `session()->regenerate()`. The middleware runs after `auth:sanctum` on every authenticated route.
- **Company deactivation**: `CompanyDeactivated` is dispatched inside the transaction of `DeactivateCompanyAction`. It is handled by a synchronous listener in Identity, which increments `session_version` and deletes reset tokens for the company's users.
- **Role changes and deactivation**: `ChangeCompanyUserRoleAction` and `DeactivateCompanyUserAction` share `AdminSeatGuardService`, which provides the lock, the transaction, and the recount (R9).
- **Dates**: All timestamps are UTC. Resources format them as ISO-8601.
- **Deptrac**: `deptrac.php` is a protected path, and this plan does not change it. The new `Identity` and `Companies` layers are not covered by deptrac until a human adds them. Until then, reviewers enforce Section I.c by hand.
- **Existing behaviour**: `routes/web.php` keeps `/` rendering `#app` (asserted by `ReportHealthTest`) and keeps `/health`.

## Assumptions (not stated by the spec)

- A company has a `name`, which is required when the company is created. The raw backlog item `specs/raw/006-companies.md` is the source. The other company details from that item are left to the companies feature.
- Email and company name are limited to 255 characters, the storage limit. Lists use Laravel's default page size of 15. The general API limit is 60 requests per minute. All of these are recorded in constraints.md.
- Accepting an invitation, or resetting a password, does not sign the user in. They go to the sign-in page.
- Deactivating or reactivating an entity that is already in that state succeeds without changing anything.
- Several pending invitations may exist for the same email, because the spec forbids inviting existing users only. The first acceptance wins, and the others then return 410.
- Invitations of a deactivated company can still be accepted, because the spec does not forbid it. The resulting user cannot sign in until the company is reactivated.
- The console command asks for the password twice with a hidden prompt and never accepts it as an argument.
- Security events are kept with no retention limit, and there is no UI to view them (a viewer UI is out of scope).
- A viewer's home page is a placeholder until the events, timesheets, and reports features exist.
