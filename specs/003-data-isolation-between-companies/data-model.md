# Data Model: Data Isolation Between Companies

**Feature**: [spec.md](./spec.md) · **Limits**: [constraints.md](./constraints.md) · **Decisions**: [research.md](./research.md)

Every model defines `casts()` for every table column. Company records are never deleted (features 001 and 002). Super-admin action records are append-only (FR-021).

## Module ownership

| Table | Owning module | Model |
|-------|---------------|-------|
| `employees` | Tenancy | `App\Modules\Tenancy\Models\Employee` |
| `terminals` | Tenancy | `App\Modules\Tenancy\Models\Terminal` |
| `access_events` | Tenancy | `App\Modules\Tenancy\Models\AccessEvent` |
| `company_media` | Tenancy | `App\Modules\Tenancy\Models\CompanyMedia` |
| `super_admin_action_records` | Tenancy | `App\Modules\Tenancy\Models\SuperAdminActionRecord` |
| `users` | Identity (existing) | uses `App\Support\BelongsToCompany`; `company_id` null only for `super_admin`; sign-in and email uniqueness are named exceptions |
| `invitations` | Identity (existing) | uses `App\Support\BelongsToCompany` |
| `company_time_zone_versions` | Companies (existing) | uses `App\Support\BelongsToCompany` |
| `company_working_day_setting_versions` | Companies (existing) | uses `App\Support\BelongsToCompany` |
| `companies` | Companies (existing) | uses `App\Support\BelongsToCompany` scoped by primary key `id` when company context is bound (`where id` equals the context company id); no `company_id` column; listed `withoutIsolation` for FR-015 |

`company_id` columns on other tables have schema foreign keys to `companies.id`. The `companies` table itself has no `company_id` column and does not gain one. Creating a company does not assign `company_id`. Tenancy code does not import `Companies\Models\Company`. It validates company existence through `Companies\PublicApi\CompanyDirectory`. `actor_id` has a schema FK to `users.id` but Tenancy has no Eloquent relation to `User`.

Same-module relations (allowed): `Terminal` `hasMany` `AccessEvent`; `Employee` `hasMany` `AccessEvent`; `AccessEvent` `belongsTo` `Terminal` and optional `Employee`; `CompanyMedia` is referenced by optional FKs from `Employee` (photo) and `AccessEvent` (snapshot) inside Tenancy.

## Company context (not a table)

Request-scoped `Tenancy\PublicApi\CompanyContext`:

| Member | Meaning |
|--------|---------|
| `companyId(): int` | Current company; throws if unbound |
| `actorId(): ?int` | Signed-in actor when set by HTTP middleware; null in jobs unless passed |
| `run(int $companyId, Closure $callback): mixed` | Bind for the callback (jobs, event ingest) |
| `withoutIsolation(Closure $callback): mixed` | Named exceptions only (contracts/isolation.md) |

Session (super admin only): allow-list of company ids this session has selected via `POST /admin/selected-company`.

## Employee (Tenancy)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `company_id` | FK → `companies.id` | required; set from context (FR-001, FR-002) |
| `employee_number` | string(32) | unique with `company_id` (FR-014); 1–32 characters |
| `name` | string(255) | required; 1–255 characters (search isolation) |
| `photo_media_id` | FK → `company_media.id`, nullable | same company |
| `created_at`, `updated_at` | timestamps | |

Unique index: (`company_id`, `employee_number`).

Uses `BelongsToCompany`. Duplicate number in the same company → `EmployeeNumberTakenException`. Same number in another company is allowed and must not be mentioned in the error.

## Terminal (Tenancy)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `company_id` | FK → `companies.id` | required |
| `name` | string(255) | required; 1–255 characters |
| `created_at`, `updated_at` | timestamps | |

Uses `BelongsToCompany`. An event from this terminal always belongs to this `company_id` (FR-011).

## AccessEvent (Tenancy)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `company_id` | FK → `companies.id` | required; copied from the terminal at receive time (spec assumption: company of the terminal at receive) |
| `terminal_id` | FK → `terminals.id` | required |
| `employee_number` | string(32) | as sent by the terminal |
| `employee_id` | FK → `employees.id`, nullable | set only when that number exists **in this company** |
| `snapshot_media_id` | FK → `company_media.id`, nullable | same company |
| `created_at` | timestamp | no `updated_at` required for isolation; include `updated_at` if the model has it and cast it |

Uses `BelongsToCompany`. Unknown terminal: no row (FR-013). Number that exists only in another company: row in the terminal's company, `employee_id` null (FR-012).

## CompanyMedia (Tenancy)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | never used in public URLs |
| `company_id` | FK → `companies.id` | required |
| `public_id` | uuid, unique | URL key (FR-009) |
| `kind` | string(32) | `CompanyMediaKind`: `employee_photo`, `event_snapshot`, `report`, `export` |
| `disk` | string(32) | `local` |
| `path` | string(255) | `{company_id}/{public_id}` under the private disk |
| `content_type` | string(127) | |
| `created_at`, `updated_at` | timestamps | |

Uses `BelongsToCompany`. Role to read (feature 001):

| Kind | company_admin | viewer | super admin (selected) |
|------|---------------|--------|------------------------|
| `employee_photo` | yes | no (403) | yes (as company admin) |
| `event_snapshot` | yes | yes | yes |
| `report` | yes | yes | yes |
| `export` | yes | yes | yes |

Guest / signed out: `401`. Signed-in other company / unknown `public_id`: same `404`.

## SuperAdminActionRecord (Tenancy)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `actor_id` | FK → `users.id` | the super admin |
| `company_id` | FK → `companies.id` | selected company |
| `type` | string(32) | `SuperAdminActionType`: `selected_company`, `changed_company_data` |
| `action` | string(64) | stable code (e.g. `tenancy.selected_company`, `identity.invite_company_user`) |
| `occurred_at` | timestamp | |

No `updated_at`. No application path to update or delete (FR-021). Not listed in the API (FR-020).

## Enums

| Type | Values |
|------|--------|
| `Tenancy\PublicApi\CompanyMediaKind` | `EmployeePhoto = 'employee_photo'`, `EventSnapshot = 'event_snapshot'`, `Report = 'report'`, `Export = 'export'` |
| `Tenancy\Data\SuperAdminActionType` | `SelectedCompany = 'selected_company'`, `ChangedCompanyData = 'changed_company_data'` |

## PublicApi types

| Type | Kind | Members |
|------|------|---------|
| `Tenancy\PublicApi\CompanyContext` | interface | `companyId()`, `actorId()`, `run()`, `withoutIsolation()` |
| `Tenancy\PublicApi\CompanyMediaKind` | backed enum | see above |
| `Tenancy\PublicApi\CrossCompanyFunctions` | final class or enum list | the named exception identifiers in contracts/isolation.md |

Identity `Actor` is unchanged (`company_id` still null for super admins).

## Domain exceptions (`app/Exceptions`)

| Exception | HTTP | Body |
|-----------|------|------|
| `CompanyNotSelectedException` | 409 | `error_code = company_not_selected`, message from `tenancy.company_not_selected`. Implements `ShouldntReport`. |
| `EmployeeNumberTakenException` | 422 | `errors.employee_number` = `__('tenancy.employee_number_taken')` — no other-company hint. Implements `ShouldntReport`. |
| `CompanyContextRequiredException` | 500 if a programmer omitted context on a company save; jobs must call `run()`. Not a client probe. Reportable (do not implement `ShouldntReport`). | |

Missing company records, including another company's ids and media, stay Laravel `404` / `ModelNotFoundException` with the same JSON as an unknown id (feature 001 FR-018 / this FR-006). Do not use a distinct error_code for "wrong company".

## Isolation probe (tests only)

`Tests\Support\Tenancy\IsolationProbe` (or a model under `tests/`): table created in the test (RefreshDatabase migration in the test case, or sqlite schema in the probe). Columns: `id`, `company_id`, `name`. Uses **only** `BelongsToCompany`. Registered on a test route included in the isolation scan (SC-008). Not shipped in production routes or swagger.
