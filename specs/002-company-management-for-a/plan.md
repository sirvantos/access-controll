# Implementation Plan: Company Management

**Branch**: `002-company-management-for-a` | **Date**: 2026-09-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-company-management-for-a/spec.md` (clarified 2026-09-28; Q1 = A, Q2 = A, Q3 = A). The limits are in [constraints.md](./constraints.md).

## Summary

This plan extends the existing `Companies` module so a company has durable details, an IANA time zone, and working day settings, with a version history that leaves past days unchanged.

- **Super admin**: create takes time zone (default `Asia/Almaty`) and optional BIN, contact person, phone, and email; the list shows BIN and is searchable; deactivate/reactivate stay idempotent and never delete (research R7, R11).
- **History**: append-only time zone and working day setting versions with a UTC `applies_from` at the next local midnight (R2–R4, R13). Profile reads the latest row; `CompanySchedule` reads the row that applies at an instant (R3, R10).
- **Company admin / viewer**: own-company GET (and admin PATCH) of details and settings, without a company id in the path (R8, R9).
- **Frontend**: extend `/companies`; add `/company` for admin edit and viewer read-only (R15).
- **Modules**: still only `PublicApi` between Companies and Identity. Timesheets will call `CompanySchedule` later; this feature does not calculate late arrivals or hours worked (R10).

## Technical Context

**Language/Version**: PHP 8.4 (`declare(strict_types=1)`), TypeScript 5.9, Vue 3.5

**Primary Dependencies**: laravel/framework ^13.17; already present from feature 001: laravel/sanctum ^4, spatie/laravel-data ^4, vue-router ^4, Pest 4, Larastan 3 (level 8), Pint, Vite 8, Vitest 5, Tailwind 4. **No new Composer or npm packages** (R11).

**Storage**: Relational database through Eloquent, engine-agnostic. Tests use in-memory SQLite. New nullable columns on `companies`, plus two append-only version tables. `name_normalized` supports Unicode-insensitive search on SQLite (R6).

**Testing**: Pest feature tests under `tests/Feature/Modules/Companies` (and an update to `tests/Feature/Modules/Identity/RoleAccessTest.php`); unit tests under `tests/Unit/Modules/Companies` for next-midnight and lookup branches; Vitest specs next to the Vue pages.

**Target Platform**: Linux server (Herd locally) and an evergreen-browser SPA.

**Project Type**: Web service with a same-origin SPA (a single Laravel project).

**Performance Goals**: SC-002 (find one company among 1,000 in under 10 seconds) is met by a paginated SQL filter at that scale. SC-001 (create in under 1 minute) is a synchronous create plus queued invitation email.

**Constraints**: All field limits come from [constraints.md](./constraints.md). Super admin must not read or change settings after create, and must not change details after create (FR-020). Tenant isolation does not reveal whether another company exists (FR-021). Octane-safe: no request state in statics. Constitution VI.a: no routine or PII logs on successful company edits.

**Scale/Scope**: Several companies, 1,000-row list target. 6 user stories, 21 functional requirements, 2 changed admin endpoints, 5 new endpoints, 1 extended SPA page, 1 new SPA page.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | How this plan complies | Status |
|-----------|------------------------|--------|
| I Layer separation | Invokable controllers call one Action and return a `JsonResource`. Actions/Services take DTOs, models, and `Actor`; they do not import Request, Session, Gate, or JsonResource. Role checks stay on `role:` middleware. | Pass |
| I.c Weak links | Identity is unchanged except RoleAccess tests and existing `FirstAdminInvitations` use on create. New `CompanySchedule` lives in `Companies\PublicApi`. No Eloquent relation or table query across modules. Http Form Requests already import `Companies\Data` (feature 001 pattern). | Pass, no exceptions needed |
| I.a SOLID / reuse | One Action per use case (create stays one Action; new show/update profile and settings Actions). Shared lookup is `CompanyScheduleService`. Validation bundles in `App\Support\Validation` (`BinRules`, `PhoneRules`, `OptionalEmailRules`) reuse `EmailRules` limits. `CompanyResource` stays the list shape; create uses a dedicated created resource. | Pass |
| I.a DTOs via Spatie Data | Form Request `toDto()` returns Spatie Data in `Companies\Data`. PublicApi views stay plain readonly types. | Pass |
| II Strict typing / no magic values | `WeekDay` enum; typed constants named after business fields, aligned with constraints.md. Larastan level 8, no ignores. | Pass |
| III Test-first | Every acceptance scenario maps to a Pest or Vitest file (quickstart §Scenario map). Endpoint tests run Controller → Form Request → Action → Resource. `CompanySchedule` unit tests cover FR-012/FR-016 instants that one HTTP save cannot fully show. Domain exceptions: none new; existing `EmailAlreadyRegisteredException` silence proof stays. | Pass |
| IV API standards | `/api/v1`, JsonResource on every success, pagination in the list Action, `snake_case`. `OkResource` is not used here. | Pass |
| VI Observability | `public/swagger.yaml` is updated from contracts/http-api.md. No new PII logs. | Pass |
| Security | Existing Sanctum SPA, CSRF, `throttle:api`, Form Requests. Own-company routes omit the id so FR-021 cannot leak existence. | Pass |
| Prohibitions | No new dependencies. Public route changes are those the spec requires (search, create fields, own-company profile/settings, time-zone list). No lowered gates. No TODO bodies. | Pass |

**Post-design re-check (after Phase 1)**: The same result. The data model adds only Companies-owned tables and same-module relations. Contracts add no Identity table access and no super-admin settings GET.

## Dependencies

None. Existing packages cover validation, IANA time zones, JSON columns, Spatie Data, Sanctum, and vue-router.

## Module boundary exceptions

None. Cross-module calls stay `App\Modules\{Other}\PublicApi\*` (R1). Schema foreign keys from Identity to `companies.id` remain as in feature 001 and are not module code.

## Project Structure

### Documentation (this feature)

```text
specs/002-company-management-for-a/
├── spec.md
├── constraints.md
├── plan.md              # this file
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   ├── http-api.md
│   └── spa-routes.md
├── checklists/requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/Admin/          # CreateCompany, ListCompanies (extended); Deactivate/Reactivate unchanged
│   ├── Controllers/Company/        # ShowCompanyProfile, UpdateCompanyProfile,
│   │                               # ShowWorkingDaySettings, UpdateWorkingDaySettings
│   ├── Controllers/                # ListTimeZones
│   ├── Requests/                   # CreateCompanyRequest (extended), UpdateCompanyProfileRequest,
│   │                               # UpdateWorkingDaySettingsRequest, ListCompaniesRequest (search)
│   └── Resources/                  # CompanyResource (add bin), CreatedCompanyResource,
│                                   # CompanyProfileResource, WorkingDaySettingsResource,
│                                   # TimeZoneIdentifiersResource
├── Modules/Companies/
│   ├── Actions/                    # CreateCompany (extended), ListCompanies (search + bin),
│   │                               # ShowCompanyProfile, UpdateCompanyProfile,
│   │                               # ShowWorkingDaySettings, UpdateWorkingDaySettings,
│   │                               # ListTimeZones, Deactivate/Reactivate (summary + bin)
│   ├── Services/                   # CompanyDirectoryService (existing),
│   │                               # CompanyScheduleService (implements CompanySchedule)
│   ├── Models/                     # Company (new columns + hasMany versions),
│   │                               # CompanyTimeZoneVersion, CompanyWorkingDaySettingVersion
│   ├── Data/                       # CreateCompanyData (extended), UpdateCompanyProfileData,
│   │                               # UpdateWorkingDaySettingsData, WorkingDaySettingDefaults
│   └── PublicApi/                  # CompanyDirectory, CompanySummary (+ bin), CompanyDeactivated,
│                                   # WeekDay, WorkingDaySettingsView, CompanyProfileView,
│                                   # CreatedCompanyView, CompanySchedule
├── Rules/Companies/                # WorkingDaySettingsRangeRule
├── Support/Validation/             # BinRules, PhoneRules, OptionalEmailRules
database/migrations/                # alter companies; create the two version tables; backfill (R14)
database/factories/                 # CompanyFactory columns + default versions; version factories if needed
lang/{en,ru}/                       # companies.php, validation attributes
routes/api.php
public/swagger.yaml
resources/js/
├── api/adminCompanies.ts           # search, bin, create extras, listTimeZones
├── api/companyProfile.ts           # new
├── pages/CompaniesPage.vue         # search, BIN column, create fields
├── pages/CompanyProfilePage.vue    # new
├── components/AppHeader.vue        # role links
├── router/index.ts
├── locales/{en,ru}.json
└── **/__tests__/*.spec.ts
tests/
├── Support/Companies/              # default settings payload, sample BIN/phone (reuse Identity company helpers)
├── Feature/Modules/Companies/      # quickstart §Scenario map
├── Feature/Modules/Identity/       # RoleAccessTest update (R9)
└── Unit/Modules/Companies/         # CompanyScheduleService next-midnight and lookup
```

**Structure Decision**: The same single Laravel project and `app/Modules/Companies` layout as feature 001. HTTP stays in `app/Http`. The SPA stays in `resources/js`. `deptrac.php` is a protected path and is not changed; reviewers enforce Section I.c by hand until a human adds a Companies layer there.

## Implementation notes for tasks

- **Create transaction**: `CreateCompanyAction` writes the company row (including `name_normalized`), the first time zone version, the first settings version, and `FirstAdminInvitations::invite()` in one `DB::transaction`. A registered first-admin email still leaves no company (and no versions).
- **List search**: `ListCompaniesRequest` validates `search`; the Action applies R6 and paginates 15 (`ListCompaniesAction::COMPANY_LIST_PAGE_SIZE`, already aligned with constraints.md).
- **Deactivate/reactivate summaries**: include `bin` so `CompanyResource` stays one shape.
- **Schedule service**: used by update Actions to compute `applies_from` and by tests to prove past instants. HTTP profile GET does not call it; it reads the latest version row.
- **Viewer vs RoleAccessTest**: after the new routes exist, the viewer loop over `/api/v1/company/*` must allow the two GET profile/settings routes and keep 403 on every other method and path (R9).
- **Existing CreateCompanyTest / ListCompaniesTest**: additive JSON fields (`bin`, create snapshot extras). Keep the 001 assertions; add 002 coverage in new or extended files rather than weakening 001 cases.
- **Dates**: store UTC; Resources emit ISO-8601 for timestamps and `HH:MM` for times of day.
- **Factory**: `CompanyFactory` `afterCreating` inserts default versions so feature 001 tests that `Company::factory()->create()` stay valid (R14).
- **No history screen and no actor_id** on versions (spec assumptions).

## Assumptions (not stated by the spec)

- List sort stays `id` ascending, as in feature 001; the spec does not define another order.
- PATCH of profile is partial: omitted name and time zone are unchanged; optional details sent empty become `NULL`.
- PATCH of working day settings is a full document (all six fields), because they are one settings set.
- `GET /time-zones` exists so the dropdown cannot drift from `timezone:all` (R5); it is not a user-facing product concept beyond "recognised time zones".
- Empty optional details are stored as SQL `NULL` (the empty state in the UI).
- Header navigation is added because AppHeader today has no links and a viewer would otherwise have no way to open the profile (US6).
