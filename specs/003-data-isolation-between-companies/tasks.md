---
description: "Task list for Data Isolation Between Companies"
---

# Tasks: Data Isolation Between Companies

**Input**: Design documents from `specs/003-data-isolation-between-companies/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [constraints.md](./constraints.md), [contracts/http-api.md](./contracts/http-api.md), [contracts/spa-routes.md](./contracts/spa-routes.md), [contracts/isolation.md](./contracts/isolation.md), [contracts/terminal-events.md](./contracts/terminal-events.md), [quickstart.md](./quickstart.md)

**Tests**: Required. Constitution §III (test-first) and [quickstart.md](./quickstart.md) §Scenario map ask for a Pest or Vitest case for every acceptance scenario. The factory runs `make verify` after each task, so every task writes its tests first and then the code that makes them pass. A task never leaves a failing test behind. Keep every feature 001/002 assertion in `RoleAccessTest`, `TenantScopingTest`, `ShowCompanyProfileTest`, `UpdateCompanyProfileTest`, sign-in, invite, and company list/create/deactivate tests; add 003 coverage without weakening those cases.

**Organization**: Tasks are grouped by user story. Phases follow the spec's priorities: P1 (US1–US5), then P2 (US6, US7).

## Format: `[ID] [P?] [Story] Description (depends on …)`

- **[P]**: Can run in parallel with the other [P] tasks of the same phase (different files, no dependency between them).
- **[Story]**: The user story the task serves (US1…US7).
- `(depends on …)` at the end of a line lists the tasks that must be done first. It never points at a later phase.

## Rules for every task

- Limits come from [constraints.md](./constraints.md). Each limit becomes a typed class constant named after the business field. Do not repeat bare numbers. Named constants: super admin company-context header `X-Company-Context`; employee number "required; 1 to 32 characters; unique within one company"; employee name "1 to 255 characters"; terminal name "1 to 255 characters"; media public id "UUID (36 characters)"; page size 15 and 60 requests per minute stay as in feature 001.
- Every PHP file starts with `declare(strict_types=1);`. Every model defines `casts()` for every column. Guards use `throw_if()` / `throw_unless()`. Closures are arrow functions.
- Business code lives in `app/Modules/Tenancy`. Identity and Companies import only `App\Modules\Tenancy\PublicApi\*`. They use `App\Support\BelongsToCompany` and MUST NOT import Tenancy models. Tenancy imports only `App\Modules\{Identity,Companies}\PublicApi\*`. Http Form Requests may import `Tenancy\Data` (feature 001 pattern). Plan §Module boundary exceptions: none. Schema FKs to `companies.id` live in migrations, not in Tenancy models as Eloquent relations to `Companies\Models\Company`. `actor_id` has a schema FK to `users.id` and no Eloquent relation to `User`.
- Every domain exception lives in `app/Exceptions` and renders the body from data-model §Domain exceptions. `CompanyNotSelectedException` (409, `error_code = company_not_selected`) and `EmployeeNumberTakenException` (422, `errors.employee_number`) implement `Illuminate\Contracts\Debug\ShouldntReport` and each need at least one `Log::fake()` silence proof. `CompanyContextRequiredException` is a 500 for a programmer-omitted context and MUST stay reportable (do not implement `ShouldntReport`). Missing company records, including another company's ids and media, stay Laravel `404` / `ModelNotFoundException` with the same JSON as an unknown id. Do not use a distinct `error_code` for "wrong company".
- Module input DTOs in `app/Modules/Tenancy/Data` extend `Spatie\LaravelData\Data`. Form Request `toDto()` builds them with Laravel typed request helpers. Do not trim; `TrimStrings` already does it.
- Every user-facing message has a key in `lang/en/*.php` and `lang/ru/*.php` (backend), or in `resources/js/locales/en.json` and `ru.json` (frontend). Tests compare against `__()` or `t()` keys, never pasted sentences.
- Every new `/api/v1` route is documented in `public/swagger.yaml` in the same task (checked by `tests/Feature/OpenApiDocumentTest.php`). Do not document `/_test/*` probe routes.
- Tests use fixed sample values from `tests/Support/Identity` (`acmeCompany()`, `globexCompany()`, `acmeAdmin()`, `globexAdmin()`, `ownerSuperAdmin()`) and `tests/Support/Tenancy`: overlapping employee number `'17'`, Globex-only name `Aigerim Sarsenova`. Super-admin company-data calls: `POST /api/v1/admin/selected-company` then header `X-Company-Context`. Compare 404 bodies of a Globex id and an unused id with `assertExactJson` (or equivalent). `Storage::fake()` on the private disk for media. `Carbon::setTestNow()` at a whole second when instants matter.
- `deptrac.php` is protected and is not changed (plan §Structure Decision). Reviewers check the module boundary by hand.
- No new Composer or npm packages (research R11). No public employee/terminal/timesheet/report/export product screens or public terminal ingest URI (research R1, R8). No Pinia (research R13). No PII in logs or in `super_admin_action_records` (constitution VI.a).
- Resources keep the Laravel `{ data: ... }` wrap except `OkResource`. Successful `GET /company/media/{public_id}` returns file bytes through a `JsonResource` (`CompanyMediaResource`) that streams the stored file with the stored `Content-Type` rather than a `{ data }` JSON envelope; errors on that route stay JSON `401`/`403`/`404`.
- Isolation must apply when a function has no extra company `where` clauses (FR-023). Octane-safe: no request company id in statics; `CompanyContext` is container-scoped and flushed.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Language files and typed limits. The Laravel project, Sanctum, Identity, and Companies already exist from features 001 and 002.

- [ ] T001 Add `lang/en/tenancy.php` and `lang/ru/tenancy.php` with the same dotted keys (start as empty arrays, matching the feature 001 `identity.php` pattern). Keys added later in this feature (`tenancy.company_not_selected`, `tenancy.employee_number_taken`, and any select-company validation messages) go in these files, not hardcoded in Form Requests. Keep `tests/Feature/LocalizationParityTest.php` green (every `lang/en` file has a `lang/ru` twin with the same keys, and the reverse).
- [ ] T002 [P] Add typed limits in `app/Modules/Tenancy/Data/TenancyLimits.php` (or equivalent Tenancy Data class) aligned with [constraints.md](./constraints.md): header name `X-Company-Context`; employee number max length 32 (required, 1 to 32 characters); employee name max length 255 (1 to 255 characters); terminal name max length 255 (1 to 255 characters). Test first in `tests/Unit/Modules/Tenancy/TenancyLimitsTest.php`: constants match those strings and lengths.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tenancy module, request-scoped company context, default Eloquent isolation, tenancy tables, and existing Identity/Companies models using `BelongsToCompany`. Every story needs them. Super admin stays refused on `/company/*` until US6.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T003 Create `app/Modules/Tenancy/TenancyServiceProvider.php`, register it in `bootstrap/providers.php`, and add `app/Support/CompanyContextStore.php` (no Tenancy import, MUST NOT name `App\Exceptions`), `app/Modules/Tenancy/PublicApi/CompanyContext.php`, plus `app/Modules/Tenancy/Services/CompanyContextService.php` (research R2). The service reads and writes the Support store and is the PublicApi implementation. When the store is unbound, it invokes a handler registered by `CompanyContextService`; that service throws `CompanyContextRequiredException` (Tenancy may use Exceptions). Interface members: `companyId(): int` (throws if unbound), `actorId(): ?int`, `run(int $companyId, Closure $callback): mixed`, `withoutIsolation(Closure $callback): mixed`. Bind the interface as a **scoped** container binding and flush it between Octane requests / at the end of HTTP tests so the id cannot leak. Actions read the PublicApi type; they do not import `Request`, `Session`, or `Gate`. The Eloquent scope reads the Support store, not Tenancy. Test first in `tests/Unit/Modules/Tenancy/CompanyContextServiceTest.php`: `run` binds for the callback and restores after; nested `run` restores the outer id; unbound `companyId()` throws `CompanyContextRequiredException`; `withoutIsolation` is the only way to query when unbound without throwing. (depends on T005)
- [ ] T004 [P] Implement `app/Support/CompanyIsolationScope.php` and `app/Support/BelongsToCompany.php` (research R4, FR-023). They read `app/Support/CompanyContextStore.php` only and MUST NOT import Tenancy or name `App\Exceptions`. Do not put the scope in Tenancy PublicApi. The default scope column is `company_id`: while context is bound, those queries add `where company_id = context.companyId()`, and on `creating` set `company_id` from context and **overwrite** any other company id in the attributes (FR-002). A model may declare another scope column. `Company` declares `id` (`where id = context.companyId()`); do not add a `company_id` column to `companies` and do not assign `company_id` when creating a `Company`. While unbound, a query or save goes through the Support store's registered handler (`CompanyContextService` throws `CompanyContextRequiredException`) unless inside `withoutIsolation`. `withoutIsolation` skips creating-assignment and the no-company refusal. A `User` may keep `company_id` null only when the role is `super_admin`. Every other company record still refuses a null company. `find` / route ids outside the company therefore 404 like a missing row (FR-006), including `ModelNotFoundException`. Test first in `tests/Unit/Support/CompanyIsolationScopeTest.php` using a throwaway in-test model or the later Tenancy models once T008 exists: bound query hides the other company; creating overwrites a planted `company_id`; a model scoped by `id` filters on `id` and does not write `company_id`; unbound save/query throws; `withoutIsolation` create keeps an explicit `company_id` and allows a `User` with `company_id` null only for `super_admin`. (depends on T003)
- [ ] T005 Add domain exceptions in `app/Exceptions/` (data-model §Domain exceptions): `CompanyNotSelectedException` (409, `error_code = company_not_selected`, message `__('tenancy.company_not_selected')`); `EmployeeNumberTakenException` (422, `errors.employee_number` = `__('tenancy.employee_number_taken')` — no other-company hint); `CompanyContextRequiredException` (500 for a programmer-omitted context; not a client probe). `CompanyNotSelectedException` and `EmployeeNumberTakenException` implement `ShouldntReport`. Leave `CompanyContextRequiredException` reportable (do not implement `ShouldntReport`). Add the message keys to `lang/en/tenancy.php` and `lang/ru/tenancy.php`. Test first in `tests/Feature/Modules/Tenancy/TenancyExceptionTest.php` (or unit + HTTP when routes exist): each exception renders the documented body under `en` and `ru`; `Log::fake()` silence proofs for `CompanyNotSelectedException` and `EmployeeNumberTakenException` (call `report($e)` if HTTP is not yet available). (depends on T001)
- [ ] T006 [P] Add `app/Modules/Tenancy/PublicApi/CompanyMediaKind.php` (`EmployeePhoto = 'employee_photo'`, `EventSnapshot = 'event_snapshot'`, `Report = 'report'`, `Export = 'export'`), `app/Modules/Tenancy/Data/SuperAdminActionType.php` (`SelectedCompany = 'selected_company'`, `ChangedCompanyData = 'changed_company_data'`), and `app/Modules/Tenancy/PublicApi/CrossCompanyFunctions.php` listing the identifier strings in [contracts/isolation.md](./contracts/isolation.md) (`admin.list_companies`, `admin.create_company`, `admin.deactivate_company`, `admin.reactivate_company`, `admin.first_admin_invitations`, `admin.select_company`, `identity.sign_in`, `identity.email_uniqueness`, `identity.create_super_admin`, `identity.password_reset`, `identity.end_company_sessions`, `identity.session_user`, `companies.directory_before_context`, `identity.show_invitation`, `identity.accept_invitation`, `tenancy.record_terminal_event.load_terminal`, `time_zones.list`, `auth.me_sign_out`). Test first in `tests/Unit/Modules/Tenancy/CrossCompanyFunctionsTest.php` and `CompanyMediaKindTest.php`: every isolation.md identifier is present; enum values match the data-model table. (depends on T003)
- [ ] T007 Create Tenancy tables in `database/migrations/` (data-model; FKs to `companies.id`). Table `employees`: `id` bigint PK; `company_id` FK required; `employee_number` string(32) ("required; 1 to 32 characters; unique within one company"); `name` string(255) ("1 to 255 characters"); `photo_media_id` nullable FK → `company_media.id`; timestamps; unique index (`company_id`, `employee_number`). Table `terminals`: `id`; `company_id` FK required; `name` string(255) ("1 to 255 characters"); timestamps. Table `access_events`: `id`; `company_id` FK required (copied from the terminal at receive time); `terminal_id` FK → `terminals.id` required; `employee_number` string(32); `employee_id` nullable FK → `employees.id`; `snapshot_media_id` nullable FK → `company_media.id`; `created_at` (include `updated_at` if the model has it and cast it). Table `company_media`: `id` bigint PK (never used in public URLs); `company_id` FK required; `public_id` uuid unique ("UUID (36 characters)"); `kind` string(32); `disk` string(32) (`local`); `path` string(255) (`{company_id}/{public_id}` under the private disk); `content_type` string(127); timestamps. Table `super_admin_action_records`: `id`; `actor_id` FK → `users.id`; `company_id` FK → `companies.id`; `type` string(32); `action` string(64); `occurred_at` timestamp; no `updated_at`. No application path to update or delete this table. Order migrations so `company_media` exists before FKs from `employees` / `access_events`. Test first in `tests/Feature/Modules/Tenancy/TenancySchemaTest.php`: unique (`company_id`, `employee_number`); same `employee_number` allowed in two companies; `public_id` unique; action table has no `updated_at`. (depends on T002)
- [ ] T008 Add models with `casts()` for every column, `BelongsToCompany`, and factories (data-model §Module ownership): `app/Modules/Tenancy/Models/Employee.php`, `Terminal.php`, `AccessEvent.php`, `CompanyMedia.php`, `SuperAdminActionRecord.php`. Same-module relations only: `Terminal` `hasMany` `AccessEvent`; `Employee` `hasMany` `AccessEvent`; `AccessEvent` `belongsTo` `Terminal` and optional `Employee`; optional FKs to `CompanyMedia` from `Employee` (photo) and `AccessEvent` (snapshot). No relation to `Company` or `User`. `SuperAdminActionRecord` is append-only (no update/delete methods). `withoutIsolation` skips creating-assignment and the no-company refusal. `SuperAdminActionRecord` keeps the company id passed into `SelectCompanyAction` and does not read an unbound context. Factories in `database/factories/`: `EmployeeFactory` (requires `company_id`; default number a non-conflicting sample, not `'17'` unless the test sets it), `TerminalFactory`, `AccessEventFactory`, `CompanyMediaFactory` (`kind` via `CompanyMediaKind`, `public_id` UUID, `disk` `local`). Test first in `tests/Feature/Modules/Tenancy/TenancyModelTest.php`: casts, relations, factory create inside `CompanyContext::run($acmeId, …)` or `withoutIsolation`, and that `Employee::factory` cannot persist without a company. (depends on T004, T006, T007)
- [ ] T009 [P] Add overlapping seeds in `tests/Support/Tenancy/` loaded from `tests/Pest.php` (quickstart §Automated gates). Reuse `acmeCompany()` / `globexCompany()`; do not invent a second company family. Helpers with `$overrides = []`: Acme employee number `'17'` with a non-Sarsenova name; Globex employee number `'17'` named `Aigerim Sarsenova`; terminals of the same name in both companies; optional media rows. Test first in `tests/Feature/Support/TenancyFixturesTest.php`: each helper returns the documented concrete values (number `'17'`, Globex name `Aigerim Sarsenova`). (depends on T008)
- [ ] T010 Apply `App\Support\BelongsToCompany` to existing company records and wrap every named exception (research R5, R12; [contracts/isolation.md](./contracts/isolation.md)). Trait on `app/Modules/Identity/Models/User.php` and `Invitation.php` (column `company_id`); a `User` may keep `company_id` null only when the role is `super_admin`. `app/Modules/Companies/Models/Company.php` with scope column `id` (no `company_id` column and no `company_id` assignment; bound queries use `where id = context.companyId()`); `CompanyTimeZoneVersion.php` and `CompanyWorkingDaySettingVersion.php` (column `company_id`). Companies code MUST NOT import Tenancy models. `withoutIsolation` skips creating-assignment and the no-company refusal. `SignInSecurityEvent` is **not** scoped. Wrap `withoutIsolation` (or `run($companyId)` where isolation.md prefers it) in: `app/Modules/Identity/Actions/SignInAction.php` and `User::findByEmail` / `emailIsRegistered` (`identity.sign_in`, `identity.email_uniqueness`); session User retrieval (`retrieveById` / auth provider) (`identity.session_user`); `CreateSuperAdminAction.php` (`identity.create_super_admin`); password-reset lookup in `RequestPasswordResetAction.php` and `ResetPasswordAction.php` (`identity.password_reset`); `EndCompanySessionsAction.php` via `CompanyContext::run($event->companyId)` (`identity.end_company_sessions`); `ShowInvitationAction.php`, `AcceptInvitationAction.php`, and `InvitationTokenService::findByToken` (`identity.show_invitation`, `identity.accept_invitation`) — accept keeps the invitation `company_id` on the new user; `CompanyDirectoryService::exists` / `isActive` (`companies.directory_before_context`); `ListCompaniesAction.php`, `CreateCompanyAction.php`, `DeactivateCompanyAction.php`, `ReactivateCompanyAction.php` (`admin.list_companies` / create / deactivate / reactivate); first-admin invitation Actions/controllers under `/admin/companies/{company}/invitations*` (`admin.first_admin_invitations`). Existing User/Invitation/Company factories must still create in tests: factory `configure()` may call `withoutIsolation` when unbound so 001/002 tests stay green. Keep `tests/Feature/Modules/Identity/SignInTest.php`, `InviteCompanyUserTest.php`, `AcceptInvitationTest.php`, `ListCompaniesTest.php`, `CreateCompanyTest.php`, `DeactivateCompanyTest.php`, `FirstAdminInvitationsTest.php`, and `UserModelTest.php` passing. Test first by extending those files only if a gap appears, plus `tests/Unit/Modules/Tenancy/NamedExceptionIsolationTest.php`: sign-in still finds a user with no HTTP company context; `ListCompaniesAction` still returns Acme and Globex with no context; `CreateSuperAdminAction` stores `company_id` null; accept invitation keeps the invitation `company_id` on the new user. (depends on T004, T006, T008)
- [ ] T011 Add `app/Http/Middleware/EnsureCompanyContext.php` (alias `company-context` in `bootstrap/app.php`). Middleware order on company-data routes: `auth:sanctum`, `current-session`, `role:…`, then `company-context` (plan §Implementation notes). Attach it to every `/api/v1/company` and `/api/v1/company/*` route in `routes/api.php`. Do **not** attach it to `/admin/companies` list/create/deactivate/reactivate, first-admin invitations, `/me`, `/time-zones`, or guest auth routes. For `company_admin` and `viewer`: ignore header `X-Company-Context`; bind `CompanyContext` from `Actor::actorCompanyId()` (constraints.md). Super admin is still not on these `role:` lists (US6). Switch company-data Actions that today call `Actor::actorCompanyId()` to `CompanyContext::companyId()` while still taking `Actor` when the use case needs who acted: `app/Modules/Companies/Actions/ShowCompanyProfileAction.php`, `UpdateCompanyProfileAction.php`, `ShowWorkingDaySettingsAction.php`, `UpdateWorkingDaySettingsAction.php`; `app/Modules/Identity/Actions/ListCompanyUsersAction.php`, `InviteCompanyUserAction.php`, `ListPendingInvitationsAction.php`, `ResendInvitationAction.php`, `RevokeInvitationAction.php`, `DeactivateCompanyUserAction.php`, `ReactivateCompanyUserAction.php`, `ChangeCompanyUserRoleAction.php`; matching controllers in `app/Http/Controllers/Company/` that currently pass `actorCompanyId()` into Actions. Submitting a `company_id` field on create/update bodies is ignored; the record stays in the context company (FR-002). Test first in `tests/Feature/Modules/Tenancy/CompanyContextMiddlewareTest.php`: Acme admin GET `/company` succeeds; Acme admin sending `X-Company-Context` for Globex still sees only Acme (FR-002, FR-004); guest 401 unchanged. Keep `tests/Feature/Modules/Companies/ShowCompanyProfileTest.php` and `tests/Feature/Modules/Identity/ListCompanyUsersTest.php` green. Super admin on `/company` remains 403 until US6. (depends on T003, T010)

**Checkpoint**: Foundation ready. Company users are isolated by default on existing `/company*` routes. User story implementation can begin.

---

## Phase 3: User Story 1 - Company users see only their own company's data everywhere (Priority: P1) 🎯 MVP

**Goal**: Every list, search, counter, report, and export a company admin or viewer sees contains only their company. Overlapping names and employee numbers in another company never appear or affect totals.

**Independent Test**: Seed Acme and Globex with overlapping employees (number `'17'`, Globex-only `Aigerim Sarsenova`), terminals of the same name, and events on the same day. As Acme admin, open every production `/api/v1/company*` list, search, counter, report, and export surface that exists; confirm only Acme data and Acme totals.

- [ ] T012 [US1] Implement `tests/Feature/Modules/Tenancy/CompanyIsolationScanTest.php` (research R10, FR-026, SC-001; [contracts/isolation.md](./contracts/isolation.md) scan rules 1–2). Materialize every `/api/v1/company*` route (same enumeration idea as `RoleAccessTest`, except HEAD). As Acme admin against Globex rows with overlapping names and `'17'`: list/search/filter/page responses contain no Globex ids, names, or emails; counters derived from those responses match Acme only. Skip named-exception routes. Keep `tests/Feature/Modules/Identity/TenantScopingTest.php` cases. Extend this scan in later stories when media and probe routes exist; in this task cover every **production** company-data route from features 001 and 002. Change application code only if the scan exposes a leak. (depends on T009, T011)
- [ ] T013 [P] [US1] Prove search, counters, and overlapping identifiers at the model layer where no public employee/report list exists yet (US1 scenarios 2–6, FR-005; research R1). In `tests/Feature/Modules/Tenancy/CompanyQueryIsolationTest.php`: under Acme context, `Employee::query()` does not return Globex `Aigerim Sarsenova` or Globex `'17'`; Acme employee count is Acme's only; `Terminal` and `AccessEvent` queries with the same names/days stay Acme-only; filter/sort/page of those queries never reveal Globex. Use T009 helpers. Change `BelongsToCompany` only if a test exposes a gap. (depends on T009, T011)

**Checkpoint**: US1 works on its own for every current company-data function. Later product lists inherit the same scope.

---

## Phase 4: User Story 2 - Another company's record looks like it does not exist (Priority: P1)

**Goal**: Opening, changing, deleting, or referring to another company's record returns the same body as an identifier that exists nowhere, and nothing in the other company changes.

**Independent Test**: For each existing record kind, take a Globex id and an unused id. As Acme admin, open/change/delete both; the two JSON bodies are identical and Globex is unchanged.

- [ ] T014 [US2] Extend `tests/Feature/Modules/Identity/TenantScopingTest.php` and `tests/Feature/Modules/Tenancy/CompanyIsolationScanTest.php` (US2 scenarios 1, 2, 4; FR-006, SC-002; isolation.md scan rule 3). For every materialized `/api/v1/company*` method that takes an id: Globex id returns **byte-for-byte the same JSON** as an unused id (`assertExactJson`); Globex rows are unchanged. Cover users, invitations, and any other parameterized company-data routes from 001/002. Keep feature 001 404 envelope (`{ "message": "..." }`, no extra `error_code` for wrong company). Change application code only if a test exposes a gap. (depends on T012)
- [ ] T015 [US2] Prove a submitted reference to another company's record is treated as not found and nothing is saved (US2 scenario 3, FR-007). In `tests/Feature/Modules/Tenancy/CrossCompanyReferenceTest.php`: under Acme context, resolving a Globex `Employee` / `Terminal` / `CompanyMedia` id throws the same `ModelNotFoundException` as an unused id; assigning a Globex `photo_media_id` or `terminal_id` on an Acme row is refused and Globex is unchanged. No public "link employee to terminal" product API; prove through model/Action writes. (depends on T008, T011)

**Checkpoint**: US1 and US2 work on their own.

---

## Phase 5: User Story 3 - New records belong to the creator's company automatically (Priority: P1)

**Goal**: New records are assigned to the creator's company regardless of any company named in the submitted data. Records cannot be moved. A save with no company is refused.

**Independent Test**: As Acme admin, create an invitation (existing HTTP) and a Tenancy employee/probe row; each belongs to Acme. Repeat while submitting Globex as `company_id`; the record still belongs to Acme (or is refused) and Globex is unchanged.

- [ ] T016 [US3] Implement create-assignment proofs in `tests/Feature/Modules/Tenancy/CompanyAssignmentTest.php` (US3 scenarios 1–4; FR-001–FR-003, SC-003). Cases: invitation (and any other existing create) belongs to Acme; `Employee`/`Terminal`/`CompanyMedia` created inside Acme context belong to Acme even when attributes include Globex `company_id`; editing cannot move `company_id`; unbound create throws `CompanyContextRequiredException`. Keep existing `InviteCompanyUserTest.php` assertions. Change `BelongsToCompany` creating/updating only if a test exposes a gap. (depends on T008, T011)

**Checkpoint**: US3 works on its own.

---

## Phase 6: User Story 4 - Photos and event snapshots are never publicly reachable (Priority: P1)

**Goal**: Employee photos, event snapshots, reports, and export files are private media. Bytes are returned only to a signed-in user of the owning company whose role allows that kind. Copied addresses do not work for guests, other companies, or after sign-out.

**Independent Test**: As Acme admin, copy an employee photo URL and an event snapshot URL. Request them as a guest, as Globex admin, and after sign-out; the image is not returned. Viewer is 403 on employee photos and 200 on event snapshots of Acme.

- [ ] T017 [US4] Implement `GET /api/v1/company/media/{public_id}` (contracts/http-api.md §Media; research R7; FR-008–FR-010, SC-004). `app/Modules/Tenancy/Actions/StoreCompanyMediaAction.php` writes to the private `local` disk at `{company_id}/{public_id}` (`serve` disabled; no `storage:link`, no public disk, no temporary signed URLs). `app/Http/Requests/ShowCompanyMediaRequest.php` `authorize()` holds the kind allow-list (data-model table: viewer denied `employee_photo`; viewer allowed `event_snapshot`, `report`, and `export`; company_admin allowed all four). If the UUID is missing or not in the current company, `authorize()` returns true so the Action can 404. `app/Modules/Tenancy/Actions/ShowCompanyMediaAction.php` only resolves the file by UUID `public_id` under current company context (no role or kind checks). `app/Http/Controllers/Company/ShowCompanyMediaController.php` returns `app/Http/Resources/CompanyMediaResource.php`, which streams file bytes on 200 with the stored `Content-Type`; failures stay JSON 401/403/404. Middleware: `auth:sanctum`, `current-session`, `role:company_admin,viewer`, `company-context`. Sequential `id` is not accepted. Route in `routes/api.php`; `public/swagger.yaml`. Test first in `tests/Feature/Modules/Tenancy/CompanyMediaTest.php` with `Storage::fake()`: Acme admin 200 bytes for Acme photo; guest 401 or 404 as specified (contracts: guest 401; other company / unknown UUID / not found — same JSON as each other — prefer identical 404 for Globex UUID vs unused UUID per FR-006 and isolation.md rule 4; if guest is 401, that is distinguishable from 404 and is the auth envelope, not a leak); Globex admin GET Acme `public_id` 404 JSON identical to unused UUID; after sign-out the copied UUID is not returned; viewer GET employee photo 403; viewer GET event snapshot 200 when in company; Acme responses never include Globex media. Extend `CompanyIsolationScanTest.php` with the media GET. Silence proof is not required on 404. (depends on T005, T008, T011)
- [ ] T018 [P] [US4] Add `resources/js/api/companyMedia.ts` for authenticated GET of media bytes (not JSON `apiRequest` for the 200 body) through `resources/js/api/client.ts` credentials. No public URL helper. Test first in `resources/js/api/__tests__/companyMedia.spec.ts` with mocked `fetch`: credentials included; 404 mapped like other company-data 404s. (depends on T017)

**Checkpoint**: US4 works on its own. Image addresses are not public.

---

## Phase 7: User Story 5 - Terminal events and employee numbers never cross companies (Priority: P1)

**Goal**: Events belong to the terminal's company and match employees only inside that company. Employee numbers are unique per company; the same number in another company is allowed and never mentioned in errors.

**Independent Test**: Create employee `'17'` in Acme and Globex. Send an event for `'17'` from an Acme terminal; it attaches only to Acme's employee. Globex `'17'` has no new event. Duplicate number in Acme is refused without revealing Globex.

- [ ] T019 [US5] Implement `app/Modules/Tenancy/Actions/CreateEmployeeAction.php` (contracts/terminal-events.md; FR-014). Input: name (1 to 255 characters) and employee number (required; 1 to 32 characters) from context company. Same number in the context company → `EmployeeNumberTakenException` (422). Same number in Globex → Acme create succeeds; the 422 body does not mention Globex. No public employee HTTP in this feature unless a later task adds test-only routes. Test first in `tests/Feature/Modules/Tenancy/CreateEmployeeTest.php`: Acme `'17'` then second Acme `'17'` throws with `__('tenancy.employee_number_taken')` under `en` and `ru` and `Log::fake()` silence proof; Acme `'17'` while Globex already has `'17'` succeeds; unique index (`company_id`, `employee_number`) holds. (depends on T005, T008, T011)
- [ ] T020 [US5] Implement `app/Modules/Tenancy/Actions/RecordTerminalEventAction.php` (contracts/terminal-events.md; research R8; FR-011–FR-013, SC-005). Input: `terminalId` (int), `employeeNumber` (string, 1–32 characters), optional snapshot already stored in the terminal's company. No HTTP ingest. Behaviour: load terminal **without** company context (`tenancy.record_terminal_event.load_terminal`); if missing, return without inserting; `CompanyContext::run(terminal.companyId, …)`; insert `AccessEvent` with that `company_id` and `terminal_id`; match `Employee` by `employee_number` **inside this context only**; if the same number exists only in another company, leave `employee_id` null; never write another company's `company_id`. Test first in `tests/Feature/Modules/Tenancy/RecordTerminalEventTest.php` with T009 seeds: Acme terminal + `'17'` attaches only Acme employee `'17'`; Globex `'17'` unchanged; unknown terminal: no row; number that exists only in Globex: Acme event with `employee_id` null. Unit branch in `tests/Unit/Modules/Tenancy/RecordTerminalEventActionTest.php` if needed for the unknown-terminal path. (depends on T006, T008, T011)

**Checkpoint**: P1 scope complete: lists isolated, not-found identical, assignment automatic, media private, events and numbers per company.

---

## Phase 8: User Story 6 - Super admin works with company data only inside a selected company (Priority: P2)

**Goal**: Super admin sees all companies without selecting. Company data requires an explicit select. While selected, they act as a company admin of that company (including deactivated), the name is always visible, data is scoped like a company user, and selects plus data-changing actions are recorded. Views and exports are not recorded.

**Independent Test**: Without selection, `/company` is 409 `company_not_selected`. Select Acme; lists are Acme-only; Globex id is 404. Switch to Globex. Select + PATCH profile write two action rows; GET media adds none. Deactivated company: select and PATCH allowed; company stays deactivated.

- [ ] T021 [US6] Implement `POST /api/v1/admin/selected-company` and `DELETE /api/v1/admin/selected-company` (contracts/http-api.md; research R3; FR-015–FR-016, FR-019–FR-020). `role:super_admin`; **no** `company-context` middleware. `app/Http/Requests/SelectCompanyRequest.php` (`company_id` required integer, exists in `companies`; messages from `lang/{en,ru}/tenancy.php` and `validation.php`). Do not add `SelectCompanyData`. `app/Modules/Tenancy/Actions/SelectCompanyAction.php` takes the integer company id: resolve company through `Companies\PublicApi\CompanyDirectory` (add `name(int $companyId): ?string` or equivalent on `app/Modules/Companies/PublicApi/CompanyDirectory.php` and `CompanyDirectoryService.php` if missing — Tenancy must not import `Company`); unknown id → 404 same as other unknown company ids; deactivated companies are selectable (FR-022); write `SuperAdminActionRecord` type `selected_company`, action `tenancy.selected_company` (no PII). Do not import Session. `app/Modules/Tenancy/Actions/ClearSelectedCompanyAction.php`: client clear; do **not** write an action row (plan assumptions). `app/Http/Controllers/Admin/SelectCompanyController.php` passes the validated integer `company_id` into `SelectCompanyAction`, then adds the id to the **session allow-list**, and returns `app/Http/Resources/SelectedCompanyResource.php` `{ id, name }` wrapped in `{ data }` (200). `app/Http/Controllers/Admin/ClearSelectedCompanyController.php` returns `OkResource` and does **not** empty the session allow-list (other tab). Routes in `routes/api.php`; `public/swagger.yaml`. Typed session-key constant for the allow-list. `withoutIsolation` / named exception `admin.select_company` so the company can be resolved without context. `SuperAdminActionRecord` keeps the company id passed into `SelectCompanyAction` and does not read an unbound context. Test first in `tests/Feature/Modules/Tenancy/SelectCompanyTest.php`: 200 with Acme `{ id, name }`; unknown id 404; 422 invalid id; deactivated Acme selectable; DELETE 200; guest 401; company admin 403; selection row stored with actor, time, company, action; `Log::fake()` silence is not required on 200; `CompanyNotSelectedException` silence proof lands in T022. Extend `tests/Feature/Modules/Companies/CompanyDirectoryTest.php` if `name()` is added. (depends on T005, T008, T010)
- [ ] T022 [US6] Allow `super_admin` on company-data routes and enforce selection (research R6; FR-016, FR-018, FR-022, SC-006). Add `super_admin` to the `role:` list of `/company` and `/company/*` routes that a company admin (or viewer, for GETs) may call, including `GET /company/media/{public_id}`. Viewer still 403 on PATCH/POST company management. Extend `EnsureCompanyContext`: for `super_admin`, require header `X-Company-Context` with an integer id that is on this session's allow-list; missing/invalid → `CompanyNotSelectedException` (409 `company_not_selected`, not 404). Bind context to that company. Company admin/viewer still ignore the header. Update `tests/Feature/Modules/Identity/RoleAccessTest.php`: super admin **without** header is 409 on every `/company/*` (replaces today's 403); with POST select + header, the same calls succeed or fail as for that company's company admin (including `409 last_active_admin`); viewer unchanged. Test first there and in `tests/Feature/Modules/Tenancy/SelectCompanyTest.php`: no selection → `/company` 409, no company data in the body; select Acme → GET `/company` 200 Acme profile; Globex user id 404 identical to unused id; switch to Globex then only Globex; forged header for a never-selected id → 409. `Log::fake()` + `assertNothingLogged()` (or not logged `error`) on the 409 path. (depends on T011, T017, T021)
- [ ] T023 [US6] Implement `app/Modules/Tenancy/Services/SuperAdminActionRecorder.php` and register an observer in `TenancyServiceProvider` (research R9; FR-020, FR-021, SC-007). Append-only rows: `actor_id`, `company_id`, `type` (`selected_company` | `changed_company_data`), `action` (stable code, e.g. `tenancy.selected_company`, `identity.invite_company_user`, `companies.update_profile`), `occurred_at`. No `updated_at`. No HTTP to list, change, or delete. Skip recording when the actor is not a super admin, when the operation is a view or media/export download, and when `withoutIsolation` is running for 001/002 admin company functions. No PII in the row. Test first in `tests/Feature/Modules/Tenancy/SuperAdminActionRecordTest.php`: select Acme then PATCH `/company` → two rows (`selected_company`, `changed_company_data`); GET `/company` and GET media add none; invite as super admin of selected Acme writes `changed_company_data`; company admin PATCH writes none; no update/delete API (404/405 on any invented path); rows unchanged after a second PATCH except a new insert. (depends on T008, T021, T022)
- [ ] T024 [US6] Super admin on existing company pages and deactivated companies (FR-017, FR-022; Q3 = B). Extend `tests/Feature/Modules/Companies/ShowCompanyProfileTest.php` and `UpdateCompanyProfileTest.php` (and working-day settings tests if needed): super admin with Acme selected GET/PATCH `/company` like Acme admin; last-active-admin still 409; deactivated Acme: select and PATCH allowed; company stays deactivated; Acme company admin still cannot sign in (001 FR-006). Direct link without selection: 409, no data. Change Actions only if a test exposes a gap (reuse the same Actions via context). (depends on T022, T023)
- [ ] T025 [US6] Frontend selection (contracts/spa-routes.md; research R13; FR-017). `resources/js/composables/useSelectedCompany.ts`: tab-local `{ id, name }` in **`sessionStorage`** (not Pinia, not `localStorage`). `resources/js/api/selectedCompany.ts`: `POST`/`DELETE` `/api/v1/admin/selected-company`. `resources/js/api/client.ts`: send `X-Company-Context` when `currentUser.role === 'super_admin'` and this tab has a selection; on 409 `company_not_selected` navigate to `/companies` (do not require clearing storage). `resources/js/components/AppHeader.vue`: super admin always shows the selected company name when selected, otherwise the select prompt via `t()`; company admin/viewer unchanged. `resources/js/pages/CompaniesPage.vue`: select control per company (including deactivated); clear control. `resources/js/router/index.ts`: `super_admin` may open `/company` and `/company/users` **only with a selection**; without selection send to `/companies`. Copy in `resources/js/locales/en.json` and `ru.json`. Test first in `resources/js/composables/__tests__/useSelectedCompany.spec.ts` (two stored selections do not mix), `resources/js/api/__tests__/client.spec.ts` (header only for `super_admin`), `resources/js/components/__tests__/AppHeader.spec.ts` (name via `t()`), `resources/js/pages/__tests__/CompaniesPage.spec.ts` (select/clear), `resources/js/router/__tests__/router.spec.ts` (super admin without selection cannot stay on `/company` or `/company/users`; with selection both stay; company admin still cannot open `/companies`). (depends on T021, T022)

**Checkpoint**: US6 works on its own. Super admin is scoped, visible, and traced.

---

## Phase 9: User Story 7 - Isolation applies by default to every current and future part of the service (Priority: P2)

**Goal**: A new company record that only uses `BelongsToCompany` is isolated without extra work. Cross-company behaviour is an explicit named exception. Background work runs inside `CompanyContext::run` for exactly one company.

**Independent Test**: Add the test-only probe model with only `BelongsToCompany`, include it in the two-company scan, and confirm it passes. A job `run(acme)` does not read Globex. Named exceptions in isolation.md still work (company list without selection).

- [ ] T026 [US7] Add test-only `Tests\Support\Tenancy\IsolationProbe` (data-model §Isolation probe; SC-008; isolation.md). Table created in the test (RefreshDatabase migration in the test case, or sqlite schema in the probe): `id`, `company_id`, `name`. Uses **only** `BelongsToCompany`. Register `GET`/`POST` `/_test/company/isolation-probes` behind `auth:sanctum`, `current-session`, `role:company_admin,super_admin`, `company-context` from the test (not `routes/api.php`, not `public/swagger.yaml`). Include these routes in `CompanyIsolationScanTest.php`. Test first in `tests/Feature/Modules/Tenancy/IsolationProbeTest.php`: Acme POST creates an Acme row; Globex id GET is 404 identical to unused id; search/list as Acme never shows Globex `name`; no extra isolation code on the probe. (depends on T004, T012, T022)
- [ ] T027 [P] [US7] Prove background work (FR-025) in `tests/Feature/Modules/Tenancy/CompanyContextJobTest.php` (or `tests/Unit/Modules/Tenancy/CompanyContextJobTest.php`): a queued job (or sync job class under `app/Modules/Tenancy/` used only as the FR-025 proof) that calls `CompanyContext::run($acmeId, …)` does not read or write Globex employees/events; a job that omits `run` and then touches `BelongsToCompany` fails with `CompanyContextRequiredException`. Fake the queue. (depends on T003, T008)
- [ ] T028 [P] [US7] Unit-test `withoutIsolation` branches and Octane flush in `tests/Unit/Modules/Tenancy/CompanyContextServiceTest.php` (extend T003): named exceptions still list both companies; a new query not on `CrossCompanyFunctions` is not granted `withoutIsolation` by accident (assert the registry list matches isolation.md). Context flush: after `run`, a later unbound `companyId()` throws. Change application code only if a test exposes a gap. (depends on T006, T010)

**Checkpoint**: All user stories work on their own. Default isolation is proven for a model with no extra scopes.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Contract, locale, and scan completeness across stories.

- [ ] T029 Align `public/swagger.yaml` with [contracts/http-api.md](./contracts/http-api.md): `POST`/`DELETE` `/admin/selected-company`, `GET /company/media/{public_id}` (200 binary; 401/403/404 JSON), header `X-Company-Context`, 409 `company_not_selected` on company-data routes for super admin. Keep `tests/Feature/OpenApiDocumentTest.php` green. Do not document `/_test/*`. (depends on T017, T021, T022)
- [ ] T030 [P] Keep `tests/Feature/LocalizationParityTest.php` green (`lang/en` ↔ `lang/ru`; `resources/js/locales/en.json` ↔ `ru.json` same keys). Confirm no new PII logs on select/PATCH. Extend `CompanyIsolationScanTest.php` so it still passes after media, probe, and super-admin role changes (skip isolation.md named exceptions). Run the targeted commands in [quickstart.md](./quickstart.md) §Automated gates. (depends on T001, T012, T017, T025, T026)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies. T002 runs in parallel with T001.
- **Foundational (Phase 2)**: Depends on Setup. Blocks every user story. T005 (exceptions) before T003 (context uses `CompanyContextRequiredException`). T004 and T006 are parallel after T003. T008 needs T004, T006, T007. T010 needs the trait and named-exception list. T011 needs T010.
- **US1 (Phase 3)**: Depends on Foundational. T013 is parallel with T012.
- **US2 (Phase 4)**: Depends on US1 scan (T012) for HTTP 404 identity; T015 needs models from T008.
- **US3 (Phase 5)**: Depends on Foundational creating behaviour; independently testable with T016.
- **US4 (Phase 6)**: Depends on Foundational media table and `company-context`. T018 after T017.
- **US5 (Phase 7)**: Depends on Foundational employees/terminals. T019 and T020 can proceed in parallel after T011.
- **US6 (Phase 8)**: Depends on Foundational + media route for "GET media adds no action row". T022 needs T021. Frontend T025 needs T021/T022.
- **US7 (Phase 9)**: Depends on scan (T012) and super-admin context (T022) for probe routes that allow `super_admin`.
- **Polish (Phase 10)**: Depends on new HTTP routes and locale keys.

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational. MVP: existing `/company*` surfaces leak nothing.
- **User Story 2 (P1)**: Can start after T012 (scan enumeration). Independently testable with Globex vs unused ids.
- **User Story 3 (P1)**: Can start after Foundational. Independently testable with creating overwrite.
- **User Story 4 (P1)**: Can start after Foundational. Independently testable with `Storage::fake()`.
- **User Story 5 (P1)**: Can start after Foundational. Independently testable via Actions (no ingest URI).
- **User Story 6 (P2)**: Can start after Foundational. Needs T017 for the "view media is not logged" proof. Integrates with US1–US2 once super admin is on `/company/*`.
- **User Story 7 (P2)**: Can start after T012 and T022. Independently testable via probe + job.

### Within Each User Story

- Tests MUST be written and FAIL before implementation.
- Models before Actions (Phase 2).
- Actions before endpoints.
- Endpoints before the frontend that calls them.
- Story complete before moving to the next priority unless a later story only adds a proof (scan extensions).

### Parallel Opportunities

- Phase 1: T002 beside T001.
- Phase 2: T005 before T003; T004 and T006 beside each other after T003; T009 beside T010 once T008 exists.
- Phase 3: T013 beside T012.
- Phase 6: T018 beside remaining backend once T017 exists.
- Phase 7: T019 beside T020.
- Phase 9: T027 and T028 beside T026 once T003/T008 exist (T026 needs T022 for super_admin on probe if the scan uses that role).
- Phase 10: T030 beside T029 once routes exist.
- After Phase 2, US3 (T016), US4 (T017), and US5 (T019–T020) can proceed in parallel with US1/US2.

---

## Parallel Example: User Story 1

```bash
# Once Phase 2 is done:
Task: "T012 CompanyIsolationScanTest.php for every production /api/v1/company* route"
Task: "T013 CompanyQueryIsolationTest.php for Employee/Terminal/AccessEvent under Acme context"
```

## Parallel Example: Foundational

```bash
# Once T001 is done:
Task: "T005 CompanyNotSelectedException in app/Exceptions/"

# Once T003 is done:
Task: "T004 BelongsToCompany in app/Support/"
Task: "T006 CrossCompanyFunctions in app/Modules/Tenancy/PublicApi/"
```

## Parallel Example: User Story 5

```bash
# Once Phase 2 is done:
Task: "T019 CreateEmployeeAction.php uniqueness"
Task: "T020 RecordTerminalEventAction.php matching"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories).
3. Complete Phase 3: User Story 1.
4. **STOP and VALIDATE**: Acme admin scan against Globex overlapping seeds; 0 leaks on production `/company*` routes.

### Incremental Delivery

1. Setup + Foundational → context, scope, tables, existing models isolated.
2. US1 → scan of current company-data functions. MVP.
3. US2 → 404 identity and refused cross-company references.
4. US3 → create assignment and no-company refusal.
5. US4 → private media GET.
6. US5 → employee uniqueness and terminal-event matching.
7. US6 → super-admin select, header, action records.
8. US7 → probe + job + named-exception registry.
9. Polish → swagger and locale parity.

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together.
2. Once Foundational is done:
   - Developer A: User Story 1 then US2
   - Developer B: User Story 3, US4, US5
   - Developer C: User Story 6 (after T017 for media logging proof) then US7
3. Stories complete and integrate independently except US6 needing the media route for SC-007's "GET media adds none" case, and US7 needing the scan helper from US1.

---

## Notes

- [P] tasks = different files, no dependencies between those [P] tasks.
- [Story] label maps the task to spec.md user stories US1–US7.
- Each user story should be independently completable and testable.
- Verify tests fail before implementing.
- Commit after each task or logical group only when a human asks.
- Stop at any checkpoint to validate the story independently.
- Avoid: vague tasks, same-file conflicts, inventing employee/terminal/report product HTTP or a public ingest URI (research R1, R8), Pinia, new packages, PII in action rows, documenting `/_test/*` in swagger.
