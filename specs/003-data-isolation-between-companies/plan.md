# Implementation Plan: Data Isolation Between Companies

**Branch**: `003-data-isolation-between-companies` | **Date**: 2026-09-29 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/003-data-isolation-between-companies/spec.md` (clarified 2026-09-29; Q1 = B, Q2 = B, Q3 = B). The limits are in [constraints.md](./constraints.md).

## Summary

The plan adds a `Tenancy` module so every company record is isolated by default, including future models that only use `BelongsToCompany`.

- **Context**: Middleware binds a request-scoped company id. Company users always use their own company. A super admin must `POST /admin/selected-company`, then send `X-Company-Context` from per-tab `sessionStorage` (R2, R3).
- **Default isolation**: Eloquent global scope + `creating` assignment from context (R4). Cross-company behaviour is an explicit `withoutIsolation` list (R5, FR-024).
- **Super admin**: `/company/*` allows `super_admin` as a company admin of the selected company, including deactivated companies (R6, FR-022). Selects and data-changing actions are stored append-only (R9).
- **Media**: Private disk, UUID `public_id`, authenticated GET (guest/signed-out `401`; other company `404` like missing) (R7).
- **Events and numbers**: `RecordTerminalEventAction` and a unique `(company_id, employee_number)` index (R8). No public terminal protocol in this feature.
- **Proof**: Isolation scan over every company-data route; SC-008 probe model that only uses the trait (R10).

## Technical Context

**Language/Version**: PHP 8.4 (`declare(strict_types=1)`), TypeScript 5.9, Vue 3.5

**Primary Dependencies**: laravel/framework ^13.17; already present: laravel/sanctum ^4, spatie/laravel-data ^4, vue-router ^4, Pest 4, Larastan 3 (level 8), Pint, Vite 8, Vitest 5, Tailwind 4. **No new Composer or npm packages** (R11).

**Storage**: Relational database through Eloquent, engine-agnostic. Tests use in-memory SQLite. New Tenancy tables: employees, terminals, access_events, company_media, super_admin_action_records. Files on the private `local` disk. Super-admin selection allow-list in the session.

**Testing**: Pest feature tests under `tests/Feature/Modules/Tenancy` (and updates to Identity `RoleAccessTest` / `TenantScopingTest` and Companies profile tests); unit tests under `tests/Unit/Modules/Tenancy` for matching and `withoutIsolation` branches; Vitest for header, picker, and two-tab selection display.

**Target Platform**: Linux server (Herd locally) and an evergreen-browser SPA.

**Project Type**: Web service with a same-origin SPA (a single Laravel project).

**Performance Goals**: No throughput target in the spec. SC-001 is 0 leaked records in the automated scan, not a latency budget.

**Constraints**: Limits are in [constraints.md](./constraints.md). Another company's record is `404` with the same body as unknown (FR-006). Missing super-admin selection is `409 company_not_selected`, not `404`. Octane-safe: no request company id in statics. Constitution VI.a: no PII in logs or in super-admin action rows. Isolation must apply when a function has no extra company `where` clauses (FR-023).

**Scale/Scope**: Several companies, small numbers of CRM users. 7 user stories, 26 functional requirements, additive company-data behaviour for super admins, one media GET, select/clear selected company, SPA header + picker. Employee/terminal/report product screens stay later features (R1).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | How this plan complies | Status |
|-----------|------------------------|--------|
| I Layer separation | Invokable controllers call one Action and return a `JsonResource`. `CompanyContext` is set in middleware (HTTP). Actions/Services take `Actor`, DTOs, and `CompanyContext`; they do not import Request, Session, Gate, or JsonResource. `SelectCompanyController` writes the session allow-list after `SelectCompanyAction` returns (lookup + selection record only). Route role lists stay on `role:` middleware. Media kind allow-list (viewer denied `employee_photo`; viewer allowed `event_snapshot`, `report`, and `export`) lives in `ShowCompanyMediaRequest::authorize()`, not in `ShowCompanyMediaAction`. | Pass |
| I.c Weak links | Identity and Companies import only `Tenancy\PublicApi` (and existing `PublicApi` of each other). They use `App\Support\BelongsToCompany` and MUST NOT import Tenancy models. Tenancy stores `actor_id` as an integer, with no Eloquent relation to `User`. Schema FKs to `companies.id` live in migrations, not in module code. Http middleware may use `Actor` and `CompanyDirectory` PublicApi types. | Pass |
| I.a SOLID / reuse | One Action per use case (`SelectCompany`, `ClearSelectedCompany`, `ShowCompanyMedia`, `RecordTerminalEvent`, `CreateEmployee` uniqueness). Shared capability: `CompanyContext`, `CompanyIsolationScope`, media serving. Existing Identity/Companies Actions are reused with context instead of `actorCompanyId()` (R12). | Pass |
| I.a DTOs via Spatie Data | `SelectCompanyRequest` validates `company_id` and passes the integer into `SelectCompanyAction` (no single-field `SelectCompanyData`). Any other new bodies still use Spatie Data in `Tenancy\Data` via Form Request `toDto()`. | Pass |
| II Strict typing / no magic values | Enums: `CompanyMediaKind`, `SuperAdminActionType`. Header name and employee-number length are typed constants aligned with constraints.md. Larastan level 8, no ignores. | Pass |
| III Test-first | Every acceptance scenario maps to Pest or Vitest (quickstart §Scenario map). Endpoint tests run Controller → Form Request → Action → Resource. `RecordTerminalEventAction` is proven in Feature tests (persistence) without a public ingest URI. `CompanyNotSelectedException` and `EmployeeNumberTakenException` have silence proofs; `CompanyContextRequiredException` stays reportable. | Pass |
| IV API standards | `/api/v1`, every controller success goes through a `JsonResource`, `snake_case`. `OkResource` only when there is no payload. 404 vs 409 as in contracts. `GET /company/media/{public_id}` 200 returns file bytes through a `JsonResource` that streams the stored file with the stored `Content-Type`. | Pass |
| VI Observability | `public/swagger.yaml` updated from contracts/http-api.md. No new PII logs. Super-admin actions go to a table, not the log. | Pass |
| Security | Existing Sanctum SPA, CSRF, `throttle:api`. Media is authenticated and company-scoped. Private disk, UUID, no public URLs. | Pass |
| Prohibitions | No new dependencies. Public route changes are those the spec requires (select company, media GET, super admin on company-data routes). No lowered gates. No TODO bodies. | Pass |

**Post-design re-check (after Phase 1)**: The same result. The data model adds only Tenancy-owned tables and same-module relations, plus `company_id` on those tables as schema FKs to `companies`. Contracts add no Identity table access from Tenancy. Named `withoutIsolation` functions are listed in contracts/isolation.md (FR-024).

## Dependencies

None. Existing packages cover sessions, Eloquent global scopes, private files, Spatie Data, Sanctum, and vue-router.

## Module boundary exceptions

None. Cross-module calls stay `App\Modules\{Other}\PublicApi\*` (R1, R5). `BelongsToCompany` and `CompanyIsolationScope` live in `app/Support` with a Support company-context store that does not import Tenancy and MUST NOT name `App\Exceptions`. Identity and Companies use that Support trait. Tenancy `CompanyContextService` reads and writes the store, remains the `PublicApi` implementation, and throws `CompanyContextRequiredException` when the store reports unbound. Schema foreign keys from Tenancy (and existing Identity) tables to `companies.id` are migrations, not module code. Do not put the scope in Tenancy `PublicApi`. Do not edit `deptrac.php`.

## Project Structure

### Documentation (this feature)

```text
specs/003-data-isolation-between-companies/
├── spec.md
├── constraints.md
├── plan.md              # this file
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   ├── http-api.md
│   ├── spa-routes.md
│   ├── isolation.md     # named exceptions, scan, probe
│   └── terminal-events.md
├── checklists/requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Exceptions/                       # CompanyNotSelectedException (409),
│                                     # CompanyContextRequiredException,
│                                     # EmployeeNumberTakenException (422)
├── Support/                          # CompanyContextStore (no Tenancy import,
│                                     # no App\Exceptions); BelongsToCompany;
│                                     # CompanyIsolationScope
├── Http/
│   ├── Controllers/Admin/            # SelectCompany, ClearSelectedCompany
│   ├── Controllers/Company/          # ShowCompanyMedia; existing company
│   │                                 # controllers keep working with context
│   ├── Middleware/                   # EnsureCompanyContext (alias company-context)
│   ├── Requests/                     # SelectCompanyRequest; ShowCompanyMediaRequest
│                                     # (kind allow-list in authorize())
│   └── Resources/                    # SelectedCompanyResource; CompanyMediaResource
│                                     # streams file bytes on 200; 404 stays JSON
├── Modules/
│   ├── Tenancy/
│   │   ├── TenancyServiceProvider.php
│   │   ├── Actions/                  # SelectCompany, ShowCompanyMedia,
│   │   │                             # RecordTerminalEvent, CreateEmployee
│   │   │                             # (uniqueness), StoreCompanyMedia
│   │   ├── Services/                 # CompanyContextService (PublicApi),
│   │   │                             # SuperAdminActionRecorder
│   │   ├── Models/                   # Employee, Terminal, AccessEvent,
│   │   │                             # CompanyMedia, SuperAdminActionRecord
│   │   ├── Data/                     # TenancyLimits (no SelectCompanyData)
│   │   └── PublicApi/                # CompanyContext, CompanyMediaKind,
│   │                                 # CrossCompanyFunctions (named exceptions)
│   ├── Identity/                     # User + Invitation use Support BelongsToCompany;
│   │                                 # Actions use CompanyContext::companyId();
│   │                                 # sign-in / email uniqueness withoutIsolation
│   └── Companies/                    # Company and version models use Support
│                                     # BelongsToCompany (Company scopes by id);
│                                     # profile/settings Actions use context;
│                                     # ListCompanies / create / deactivate
│                                     # withoutIsolation (FR-015, FR-024)
bootstrap/app.php                     # alias company-context; attach to
                                      # company-data route groups
bootstrap/providers.php               # + TenancyServiceProvider
database/migrations/                  # tenancy tables
database/factories/                   # Employee, Terminal, AccessEvent, CompanyMedia
lang/{en,ru}/                         # tenancy.php, validation
routes/api.php                        # contracts/http-api.md
public/swagger.yaml
resources/js/
├── api/client.ts                     # X-Company-Context for super_admin
├── api/selectedCompany.ts            # POST/DELETE select
├── api/companyMedia.ts               # GET media
├── composables/useSelectedCompany.ts
├── components/AppHeader.vue          # selected company name (FR-017)
├── pages/CompaniesPage.vue           # select control
├── router/index.ts                   # super_admin on /company* when selected
├── locales/{en,ru}.json
└── **/__tests__/*.spec.ts
tests/
├── Support/Tenancy/                  # isolation probe model, overlapping seeds
├── Feature/Modules/Tenancy/          # quickstart §Scenario map
├── Feature/Modules/Identity/         # RoleAccessTest, TenantScopingTest updates
├── Feature/Modules/Companies/        # super admin GET/PATCH /company with selection
└── Unit/Modules/Tenancy/             # matching, withoutIsolation, context flush
```

**Structure Decision**: The same single Laravel project. Isolation PublicApi is `app/Modules/Tenancy`. The Eloquent trait and scope live in `app/Support` so Identity and Companies do not import Tenancy internals. HTTP stays in `app/Http`. The SPA stays in `resources/js`. `deptrac.php` is a protected path and is not changed.

## Implementation notes for tasks

- **Middleware order**: `auth:sanctum`, `current-session`, `role:…`, then `company-context` on every company-data route (`/api/v1/company`, `/api/v1/company/*`). Do not attach `company-context` to `/admin/companies` list/create/deactivate/reactivate, first-admin invitations, `/me`, `/time-zones`, or guest auth routes (contracts/isolation.md).
- **Role lists**: Add `super_admin` to the `role:` list of company-data routes that a company admin (or viewer, for GETs) may call. Viewer still 403 on PATCH/POST company management.
- **Session allow-list**: `SelectCompanyController` adds the selected company id to the session after `SelectCompanyAction` returns. The Action does company lookup and the selection record only.
- **Bytes response**: One GET URL. `ShowCompanyMediaRequest::authorize()` applies the kind allow-list (viewer denied `employee_photo`; viewer allowed `event_snapshot`, `report`, and `export`). The Action only resolves the file for the current company (or 404). The controller returns a `JsonResource` that streams the file on 200. Failures stay JSON 401/403/404. No second public path.
- **Observer**: Register in `TenancyServiceProvider`. Skip recording when the actor is not a super admin, when `type` would be a view, and when `withoutIsolation` is running for 001/002 admin company functions.
- **Identity sign-in**: wrap user lookup in `withoutIsolation` so the global scope cannot hide users.
- **Email uniqueness**: existing queries stay global via `withoutIsolation`.
- **Factories**: `Employee::factory` requires `company_id`; tests set context or `withoutIsolation` when seeding two companies.
- **Dates**: UTC, ISO-8601 in JSON; media is bytes.
- **Existing tests**: Acme/Globex helpers stay; add overlapping employee number `17` in Tenancy support helpers.

## Complexity Tracking

None. `GET /company/media/{public_id}` 200 returns file bytes through a `JsonResource` that streams the stored file with the stored `Content-Type`. Error statuses on that route stay the JSON envelopes of feature 001 (`401`/`403`/`404`).

## Assumptions (not stated by the spec)

- Employee/terminal/event/report/export **product screens and public list APIs** are later features. This feature stores the rows, uniqueness, matching, and media rules those screens will use, plus a test probe (R1).
- Terminal ingest **transport** (HTTP vs AMQP vs device protocol) is later; this feature ships `RecordTerminalEventAction` only.
- Employee number is a string of 1–32 characters (constraints.md); the spec's `17` is stored as `'17'`.
- Super-admin “asked to select a company” is `409` with `error_code = company_not_selected`, not `404`.
- Clearing selection does not write a super-admin action row (FR-020 lists select and change, not clear).
- The session allow-list is the set of company ids this session has selected; it is not shown in the UI.
- Viewer access to media kinds follows feature 001 roles: event snapshots and reports yes; employee photos no.
- Media 200 is file bytes returned through a `JsonResource`; 404 JSON matches other missing records.
