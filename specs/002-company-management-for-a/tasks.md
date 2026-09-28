---

description: "Task list for Company Management"
---

# Tasks: Company Management

**Input**: Design documents from `specs/002-company-management-for-a/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [constraints.md](./constraints.md), [contracts/http-api.md](./contracts/http-api.md), [contracts/spa-routes.md](./contracts/spa-routes.md), [quickstart.md](./quickstart.md)

**Tests**: Required. Constitution §III (test-first) and [quickstart.md](./quickstart.md) §Scenario map ask for a Pest test for every acceptance scenario and a Vitest spec for every page. The factory runs `make verify` after each task, so every task writes its tests first and then the code that makes them pass. A task never leaves a failing test behind. Keep every feature 001 assertion in `CreateCompanyTest`, `ListCompaniesTest`, `DeactivateCompanyTest`, and `ReactivateCompanyTest`; add 002 coverage in those files or in new files without weakening 001 cases.

**Organization**: Tasks are grouped by user story. Phases follow the spec's priorities: P1 (US1, US2, US3), then P2 (US4, US5), then P3 (US6).

## Format: `[ID] [P?] [Story] Description (depends on …)`

- **[P]**: Can run in parallel with the other [P] tasks of the same phase (different files, no dependency between them).
- **[Story]**: The user story the task serves (US1…US6).
- `(depends on …)` at the end of a line lists the tasks that must be done first. It never points at a later phase.

## Rules for every task

- Limits come from [constraints.md](./constraints.md). Each limit becomes a typed class constant named after the business field. Do not repeat bare numbers. Named constants: `COMPANY_NAME_MAX_LENGTH` (already on `CreateCompanyRequest`), `COMPANY_BIN_LENGTH = 12`, `COMPANY_PHONE_MAX_LENGTH = 32`, `COMPANY_CONTACT_PERSON_MAX_LENGTH = 255`, `DEFAULT_TIME_ZONE = 'Asia/Almaty'`, and the FR-014 defaults on `WorkingDaySettingDefaults`.
- Every PHP file starts with `declare(strict_types=1);`. Every model defines `casts()` for every column. Guards use `throw_if()` / `throw_unless()`. Closures are arrow functions.
- Business code lives in `app/Modules/Companies`. Other modules and `app/Http` import only `App\Modules\{Other}\PublicApi\*` (plan §Module boundary exceptions: none). Http Form Requests may import `Companies\Data` (feature 001 pattern).
- No new domain exceptions. Validation stays 422, role mismatches stay 403, unknown super-admin company ids stay 404, missing delete routes stay 405. Identical saves and idempotent deactivate/reactivate are successes. Constitution VI.a: no routine or PII logs on successful company edits.
- Module input DTOs in `app/Modules/Companies/Data` extend `Spatie\LaravelData\Data`. Form Request `toDto()` builds them with Laravel typed request helpers. Lowercase emails with Stringable `->lower()`. Do not trim; `TrimStrings` already does it. Empty optional details are stored as SQL `NULL` (research R12).
- Every user-facing message has a key in `lang/en/*.php` and `lang/ru/*.php` (backend), or in `resources/js/locales/en.json` and `ru.json` (frontend). Tests compare against `__()` or `t()` keys, never pasted sentences.
- Every new `/api/v1` route is documented in `public/swagger.yaml` in the same task (checked by `tests/Feature/OpenApiDocumentTest.php`).
- Tests use fixed sample values from `tests/Support/Identity` and `tests/Support/Companies`, `Notification::fake()` on create, and `Carbon::setTestNow()` at a whole second before fixtures for FR-012 / FR-016 (frozen-clock rule).
- `deptrac.php` is protected and is not changed (plan §Structure Decision). Reviewers check the module boundary by hand.
- No new Composer or npm packages (research R11). No history screen and no `actor_id` on version rows (plan §Implementation notes).
- Resources keep the Laravel `{ data: ... }` wrap. Do not copy `OkResource`'s `$wrap = null`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Language files and shared company fixtures for this feature. The Laravel project, Sanctum, and Companies module already exist from feature 001.

- [x] T001 Add `lang/en/companies.php` and `lang/ru/companies.php` with the same dotted keys (start as empty arrays, matching the feature 001 `identity.php` pattern). Any company-specific validation message added later in this feature goes in these files, not hardcoded in Form Requests. Keep `tests/Feature/LocalizationParityTest.php` green (every `lang/en` file has a `lang/ru` twin with the same keys, and the reverse).
- [x] T002 [P] Add shared company fixtures in `tests/Support/Companies/` loaded from `tests/Pest.php` (plan §Project Structure, quickstart §Automated gates). Reuse `acmeCompany()` / `globexCompany()` from `tests/Support/Identity/helpers.php`; do not invent a second company family. Helpers with `$overrides = []`: a 12-digit BIN sample `123456789012`, a valid phone sample that meets "10 to 15 digits, optionally starting with `+`, and may contain spaces, hyphens, and parentheses; 32 characters at most", a default working-day-settings payload matching FR-014 (start `09:00`, end `18:00`, `monday` through `friday`, break `60`, `break_deducted` true, lateness grace `0`), and a create-body helper for optional details. Test first in `tests/Feature/Support/CompanyFixturesTest.php`: each helper returns the documented concrete values.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Additive company columns, append-only version tables, PublicApi schedule types, shared validation, and `CompanySchedule`. Every story needs them.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T003 Create the additive `companies` columns and the two version tables (data-model §Company, §CompanyTimeZoneVersion, §CompanyWorkingDaySettingVersion; research R2, R14). Migration in `database/migrations/`: on `companies` add `bin` string(12) nullable ("optional; exactly 12 digits when set"), `contact_person` string(255) nullable ("optional; 1 to 255 characters when set"), `phone` string(32) nullable ("optional; constraints.md phone rule when set"), `email` string(255) nullable ("optional; valid email, lowercased, 255 characters at most"), `name_normalized` string(255) (`mb_strtolower` of `name`; characters, not bytes); ordinary btree indexes on `name_normalized` and on `bin`. Table `company_time_zone_versions`: `id` bigint PK, `company_id` FK → `companies.id` required, `time_zone` string(64) ("IANA identifier; validated with `timezone:all`"), `applies_from` timestamp ("UTC instant from which this identifier applies"), `created_at` only (no `updated_at`); index (`company_id`, `applies_from`, `id`). Table `company_working_day_setting_versions`: `id` bigint PK, `company_id` FK → `companies.id` required, `start_time` time ("`HH:MM` 00:00–23:59"), `end_time` time ("later than `start_time` on the same day"), `working_days` json ("unique list of `WeekDay` values; at least one"), `break_duration_minutes` unsigned smallint ("0 ≤ value < working day length in minutes"), `break_deducted` boolean, `lateness_grace_minutes` unsigned smallint ("0 ≤ value < working day length in minutes"), `applies_from` timestamp, `created_at` only; index (`company_id`, `applies_from`, `id`). Backfill every existing `companies` row with one time zone version (`Asia/Almaty`) and one working day settings version (FR-014 defaults), `applies_from` equal to the start of that company's `created_at` day in `Asia/Almaty`. No row in these tables is ever deleted. Test first in `tests/Feature/Modules/Companies/CompanySchemaTest.php`: a migrated existing-shaped company (name only) has both version rows and `name_normalized`.
- [x] T004 Extend `app/Modules/Companies/Models/Company.php` and add the version models (data-model §Module ownership). Company: `casts()` for every new column; `fillable` includes the new attributes; `hasMany` `timeZoneVersions` and `workingDaySettingVersions` (same-module only; no Identity relations); maintain `name_normalized` as `mb_strtolower` of `name` on create and on name change. Models `app/Modules/Companies/Models/CompanyTimeZoneVersion.php` and `app/Modules/Companies/Models/CompanyWorkingDaySettingVersion.php` with `casts()` for every column (`working_days` through `WeekDay` once T005 exists — if this task lands first, store the json list and add the enum cast in T005), `belongsTo` `Company`, no `updated_at`. `database/factories/CompanyFactory.php` `afterCreating` inserts the default time zone version (`Asia/Almaty`) and FR-014 settings version so feature 001 tests that call `Company::factory()->create()` stay valid (research R14); optional version factories if needed. Test first: extend `tests/Feature/Modules/Companies/CompanyModelTest.php` for the new casts, `name_normalized`, both `hasMany` relations, and that the factory inserts one row in each version table. Keep the existing `isActive()` and factory-state cases. (depends on T003)
- [x] T005 [P] Add Companies PublicApi types and defaults (data-model §Enums, §PublicApi types, §Internal Data). Backed enum `app/Modules/Companies/PublicApi/WeekDay.php` with TitleCase keys `Monday = 'monday'` … `Sunday = 'sunday'` (constraints.md identifiers as values). Readonly DTOs: `WorkingDaySettingsView` (`startTime` `CarbonInterface` time-of-day, `endTime`, `workingDays` `list<WeekDay>`, `breakDurationMinutes`, `breakDeducted`, `latenessGraceMinutes`); `CompanyProfileView` (`id`, `name`, `timeZone`, `bin`, `contactPerson`, `phone`, `email`); `CreatedCompanyView` (`CompanyProfileView` fields plus `isActive`, `awaitingFirstAdmin`, `createdAt`, `workingDaySettings`). Interface `app/Modules/Companies/PublicApi/CompanySchedule.php` with `timeZoneIdentifierAt(int $companyId, CarbonInterface $instant): string` and `workingDaySettingsAt(int $companyId, CarbonInterface $instant): WorkingDaySettingsView`. `app/Modules/Companies/Data/WorkingDaySettingDefaults.php` with typed constants: `DEFAULT_TIME_ZONE = 'Asia/Almaty'`, start `09:00`, end `18:00`, working days `monday`–`friday`, break `60`, `break_deducted` true, grace `0`. Additive `bin` (`?string`) on existing `app/Modules/Companies/PublicApi/CompanySummary.php`; update every constructor call in `app/Modules/Companies/Actions/CreateCompanyAction.php`, `ListCompaniesAction.php`, `DeactivateCompanyAction.php`, and `ReactivateCompanyAction.php`; add `bin` (`null` when empty) to `app/Http/Resources/CompanyResource.php` so list / deactivate / reactivate stay one shape (contracts/http-api.md §Resources). Test first in `tests/Unit/Modules/Companies/WeekDayTest.php` (seven values match constraints.md) and extend `tests/Feature/Modules/Companies/ListCompaniesTest.php` to assert `data.*.bin` is present (null when unset) without dropping 001 assertions. (depends on T003)
- [x] T006 Add shared validation (data-model §Validation rule objects, constraints.md §Company fields / §Working day settings). `app/Support/Validation/BinRules.php`: optional, exactly 12 digits or empty, typed constant `COMPANY_BIN_LENGTH = 12`. `app/Support/Validation/PhoneRules.php`: optional; "10 to 15 digits, optionally starting with `+`, and may contain spaces, hyphens, and parentheses; 32 characters at most"; typed constant `COMPANY_PHONE_MAX_LENGTH = 32`. `app/Support/Validation/OptionalEmailRules.php`: optional; `email` + max 255 (reuse `EmailRules::EMAIL_MAX_LENGTH`, not required). `app/Rules/Companies/WorkingDaySettingsRangeRule.php` (DataAware, not a Form Request closure): end after start on the same day; at least one weekday; `break_duration_minutes` and `lateness_grace_minutes` integers from 0 through working-day length minus 1, where working day length in minutes is `end` minus `start` on the same local day (research R17). `app/Http/Requests` constants `COMPANY_CONTACT_PERSON_MAX_LENGTH = 255`. Laravel `timezone:all` for IANA identifiers. Messages from `lang/{en,ru}/validation.php` and `lang/{en,ru}/companies.php`. Test first in `tests/Feature/Support/Validation/CompanyValidationRulesTest.php` with `Validator::make`: BIN 11 digits / 12 digits / 13 digits / empty; phone too short / valid with spaces and `+` / over 32 characters; optional email 255 vs 256; settings end-not-after-start, empty `working_days`, break and grace equal to the working-day length. (depends on T001, T005)
- [x] T007 Implement `app/Modules/Companies/Services/CompanyScheduleService.php` (research R2, R3, R4, R10, R13) and bind `CompanySchedule` to it in `app/Modules/Companies/CompaniesServiceProvider.php`. Lookup: latest `id` among rows with `applies_from <= T`. Profile "current" is not this service — HTTP GETs read the latest version row (highest `id`). `appliesFromNextLocalMidnight(string $timeZoneIdentifier, CarbonInterface $now): CarbonInterface` computes local `Y-m-d 00:00:00` of the following day in that identifier, then UTC. `appliesFromStartOfLocalDay` for create/backfill. Never delete version rows. If the submitted payload equals the latest row, insert nothing; if it differs, insert with the same next-midnight `applies_from` as any earlier same-day change so the highest `id` wins. Test first in `tests/Unit/Modules/Companies/CompanyScheduleServiceTest.php`: freeze `Carbon::setTestNow` at `2026-01-15 12:00:00` UTC with company zone `Asia/Almaty`; stored `applies_from` equals Almaty `2026-01-16 00:00:00`; lookup for an instant still today (and any earlier instant) returns the previous version; lookup at/after `applies_from` returns the new version; identical payload inserts nothing; two differing saves before midnight share `applies_from` and the highest `id` applies. (depends on T004, T005)

**Checkpoint**: Foundation ready. User story implementation can begin.

---

## Phase 3: User Story 1 - Super admin creates a company with its details (Priority: P1) 🎯 MVP

**Goal**: A super admin creates an active company with a time zone (default `Asia/Almaty`), optional BIN / contact person / phone / email, the first-admin invitation from feature 001, and FR-014 working day settings. The 201 body is the only super-admin read of details and settings (research R7, FR-020).

**Independent Test**: As a super admin, create a company with only a name and the first admin's email; confirm it is active, time zone `Asia/Almaty`, and working day settings are the defaults. Create a second company with every optional detail and a different time zone; confirm all values are saved.

- [x] T008 [P] [US1] Implement `GET /api/v1/time-zones` behind `auth:sanctum` + `current-session` + `role:super_admin,company_admin` (contracts/http-api.md, research R5, FR-002). `app/Modules/Companies/Actions/ListTimeZonesAction.php` returns `DateTimeZone::listIdentifiers()` in that sort order. `app/Http/Controllers/ListTimeZonesController.php` in `app/Http/Controllers/`. `app/Http/Resources/TimeZoneIdentifiersResource.php` `{ identifiers: string[] }` (keep Laravel wrap). Route in `routes/api.php`; `public/swagger.yaml`. Viewer and unauthenticated callers are refused. Test first in `tests/Feature/Modules/Companies/ListTimeZonesTest.php`: 200 includes `Asia/Almaty`; viewer 403; guest 401. (depends on T005)
- [x] T009 [US1] Extend create (US1 scenarios 1–5; FR-001–FR-004, FR-014, FR-020). Additive fields on `app/Modules/Companies/Data/CreateCompanyData.php`: `timeZone` (string, default `Asia/Almaty`), `bin`, `contactPerson`, `phone`, `email` (each `?string`). `app/Http/Requests/CreateCompanyRequest.php`: `time_zone` optional, `timezone:all`, default `WorkingDaySettingDefaults::DEFAULT_TIME_ZONE`; `bin` → `BinRules`; `contact_person` optional 1–255 or empty (`COMPANY_CONTACT_PERSON_MAX_LENGTH = 255`); `phone` → `PhoneRules`; `email` → `OptionalEmailRules`; keep `name` 1–255 and `first_admin_email` → `EmailRules`; `toDto()` lowercases company email. `app/Modules/Companies/Actions/CreateCompanyAction.php` in one `DB::transaction`: company row including `name_normalized` and optional details as `NULL` when empty; first time zone version (`time_zone` from the request or `Asia/Almaty`, `applies_from` = start of the creation local day in that zone via `CompanyScheduleService`); first settings version (FR-014 defaults, same `applies_from`); `FirstAdminInvitations::invite()`. A registered first-admin email still leaves no company and no versions. Return `CreatedCompanyView`. `app/Http/Resources/CreatedCompanyResource.php` `{ id, name, time_zone, bin, contact_person, phone, email, is_active, awaiting_first_admin, created_at, working_day_settings }` with `working_day_settings` as `{ start_time, end_time, working_days, break_duration_minutes, break_deducted, lateness_grace_minutes }` (`HH:MM`, weekday identifiers). Switch `app/Http/Controllers/Admin/CreateCompanyController.php` from `CompanyResource` to `CreatedCompanyResource` (201). `public/swagger.yaml`. Test first in `tests/Feature/Modules/Companies/CreateCompanyTest.php`: keep 001 cases; add create with only name + first-admin email → 201, active, `time_zone` `Asia/Almaty`, FR-014 `working_day_settings`; create with `Europe/Moscow` and all optional details → all saved; missing name / unknown time zone / bad BIN-phone-email → 422, no row; company admin and viewer POST → 403; registered first-admin email still 422 with no company and no version rows. (depends on T002, T006, T007)
- [x] T010 [US1] Extend the super-admin create form (contracts/spa-routes.md). `resources/js/api/adminCompanies.ts`: `createCompany` accepts time zone (pre-filled `Asia/Almaty`) and optional BIN, contact person, phone, email; add `listTimeZones()`. Created response type includes `time_zone`, optional details, and `working_day_settings`. `resources/js/pages/CompaniesPage.vue`: those fields on create; time-zone options from `GET /time-zones`; 422 shown per field. Copy through `t()` in `resources/js/locales/en.json` and `ru.json`. No working-day-settings editor on this page. Test first in `resources/js/pages/__tests__/CompaniesPage.spec.ts` and `resources/js/api/__tests__/adminCompanies.spec.ts` if present (create fields and 422). (depends on T008, T009)

**Checkpoint**: US1 works on its own: create with defaults, create with full details, validation, and role refusal.

---

## Phase 4: User Story 2 - Super admin finds companies in a searchable list (Priority: P1)

**Goal**: The company list shows name, BIN, active/deactivated state, and creation date. Search by part of name or BIN ignores letter case and surrounding spaces and applies before pagination (page size 15).

**Independent Test**: Seed three companies (two active, one deactivated) with known names and BINs. As a super admin, open the list and confirm all three appear with their state; search by part of a name and by part of a BIN and confirm only the matching companies are shown.

- [x] T011 [US2] Implement list search (US2 scenarios 1–6; FR-005, FR-006; research R6). `app/Http/Requests/ListCompaniesRequest.php`: optional `search`, max 255 characters (constraints.md §Company list). `app/Http/Controllers/Admin/ListCompaniesController.php` uses that request instead of raw `Request`. `app/Modules/Companies/Actions/ListCompaniesAction.php`: when `search` is present, trim is already done; lowercase the term; filter `name_normalized LIKE %term%` OR `bin LIKE %term%`; then paginate 15 (`COMPANY_LIST_PAGE_SIZE`, already aligned) ordered by `id` ascending (plan §Assumptions). Empty match is `200` with `data: []`. `public/swagger.yaml` query `search`. Test first in `tests/Feature/Modules/Companies/ListCompaniesTest.php`: keep 001 cases; super admin sees active and deactivated with name, `bin` (if set), state, `created_at`; search `"stroy"` matches company named `"Alma Stroy"` (case-insensitive, including Cyrillic via `name_normalized`); search `"4567"` matches BIN `123456789012`; no match returns empty `data`; more than 15 companies → page size 15 and search applies before pagination; leading/trailing spaces on the term are ignored; search longer than 255 → 422; company admin and viewer GET → 403. (depends on T002, T004, T005)
- [x] T012 [US2] Extend `resources/js/pages/CompaniesPage.vue` and `resources/js/api/adminCompanies.ts` (contracts/spa-routes.md, research R15): `listCompanies(page, search?)`; BIN column (blank when empty); search field bound to `route.query` `search` together with `page` (same pattern the list already uses for `page`); empty match shows the empty-list copy via `t()`. No delete control. No opening of working day settings. Copy in both locale files. Test first in `resources/js/pages/__tests__/CompaniesPage.spec.ts`: BIN column, search query, empty copy via `t()`. (depends on T011)

**Checkpoint**: US1 and US2 work on their own.

---

## Phase 5: User Story 3 - Super admin deactivates and reactivates a company (Priority: P1)

**Goal**: Deactivate and reactivate stay idempotent and never delete. Details, settings, settings history, and time zone history are unchanged. Users lose and regain access as in feature 001.

**Independent Test**: Factory-seed a company with BIN and default versions, deactivate it, confirm the list shows deactivated with BIN and versions unchanged, reactivate it, confirm the same data. (Changing details/settings then deactivating is re-checked in T025 after US4/US5 exist.)

- [x] T013 [US3] Prove retention and no-delete (US3 scenarios 1–6; FR-007–FR-009, FR-008, SC-006). Keep feature 001 session-end behaviour in `tests/Feature/Modules/Companies/DeactivateCompanyTest.php` and `ReactivateCompanyTest.php`. Add: after deactivate, `bin`, contact fields, `name_normalized`, and both version tables are unchanged; reactivate restores `is_active` with the same rows (same ids and `applies_from`); idempotent deactivate/reactivate → 200, no error, no extra version rows; `DELETE /api/v1/admin/companies/{id}` → 405; company admin and viewer deactivate/reactivate → 403 without revealing whether the id exists (FR-021). `public/swagger.yaml` already has POST deactivate/reactivate; document 405 for DELETE if not already listed. Change application code only if a test exposes a gap (deactivate/reactivate Actions must not touch version tables or details). (depends on T004, T005, T011)
- [x] T014 [P] [US3] Confirm `resources/js/pages/CompaniesPage.vue` has no delete control and that deactivate/reactivate still update the row including BIN. Test first in `resources/js/pages/__tests__/CompaniesPage.spec.ts`. Copy through `t()` if any new labels. (depends on T012)

**Checkpoint**: P1 scope: create, search, deactivate/reactivate with data kept.

---

## Phase 6: User Story 4 - Company admin edits the company's details and time zone (Priority: P2)

**Goal**: A company admin reads and patches their own company's name, time zone, BIN, contact person, phone, and email through `/api/v1/company` (no company id in the path). A time zone change inserts a version that applies from the next local midnight; today keeps the previous identifier. Super admin is 403 after create (FR-020).

**Independent Test**: As a company admin of Acme, change each detail and clear an optional one; confirm the changes are saved. Try to change Globex through admin routes; confirm 403 without revealing Globex.

- [x] T015 [US4] Implement `GET /api/v1/company` behind `auth:sanctum` + `current-session` + `role:company_admin,viewer` (FR-019, FR-021, research R8, R9). Scoped to `Actor::actorCompanyId()`. `app/Modules/Companies/Actions/ShowCompanyProfileAction.php` reads the company row and the latest time zone version (highest `id`), not `CompanySchedule`. `app/Http/Controllers/Company/ShowCompanyProfileController.php`. `app/Http/Resources/CompanyProfileResource.php` `{ id, name, time_zone, bin, contact_person, phone, email }` (`null` when empty). Route in `routes/api.php` (own-company group; no `{company}`). `public/swagger.yaml`. Update `tests/Feature/Modules/Identity/RoleAccessTest.php` so a viewer `GET /api/v1/company` succeeds and every other materialized `/api/v1/company/*` method/path (including PATCH until T016, users, invitations) stays 403; super admin remains 403 on every `/company/*` route. Test first in `tests/Feature/Modules/Companies/ShowCompanyProfileTest.php`: Acme admin 200 with name and latest time zone; super admin 403; Acme admin `GET /admin/companies/{globex}` (if such a show route does not exist, assert 403/404 on the admin path used as a probe) does not reveal Globex; guest 401. (depends on T004, T007)
- [x] T016 [US4] Implement `PATCH /api/v1/company` behind `role:company_admin` (US4 scenarios 1–5; FR-010, FR-012, FR-019, FR-020). `app/Modules/Companies/Data/UpdateCompanyProfileData.php`: `name` (`?string`), `timeZone` (`?string`), `bin`, `contactPerson`, `phone`, `email` (nullable optionals; omitted name/time zone stay unchanged). `app/Http/Requests/UpdateCompanyProfileRequest.php`: when present, `name` required 1–255; `time_zone` `timezone:all`; optionals via `BinRules` / contact-person max / `PhoneRules` / `OptionalEmailRules`; empty optionals become `NULL`. `app/Modules/Companies/Actions/UpdateCompanyProfileAction.php`: persist details; on name change update `name_normalized`; if the time zone identifier equals the latest row, insert nothing; otherwise insert a version with `applies_from` = next local midnight in the zone in effect at `now()` (`CompanySchedule::timeZoneIdentifierAt` at `now()`, still the previous identifier until that midnight). Return `CompanyProfileView` (latest row). `app/Http/Controllers/Company/UpdateCompanyProfileController.php` returns `CompanyProfileResource`. Viewer and super admin PATCH → 403. `public/swagger.yaml`. `DELETE /company` is not defined (405). Test first in `tests/Feature/Modules/Companies/UpdateCompanyProfileTest.php`: Acme admin PATCH valid fields and clear an optional → 200 and GET shows it; empty name / unknown zone / bad format → 422, unchanged; time zone change → latest row is the new identifier; `CompanySchedule::timeZoneIdentifierAt` for an instant still today returns the previous identifier; after `applies_from` it returns the new one; several time zone saves the same day → highest `id` applies from that midnight; identical time zone inserts nothing; Acme admin GET/PATCH Globex via `/admin/companies/{globex}` → 403; super admin PATCH `/company` → 403. (depends on T006, T015)
- [x] T017 [US4] Add `resources/js/api/companyProfile.ts` (`showCompanyProfile`, `updateCompanyProfile`, reuse `listTimeZones` from `adminCompanies.ts` or call `GET /time-zones` here without duplicating the HTTP helper) and `resources/js/pages/CompanyProfilePage.vue` at `/company` with `meta.roles = ['company_admin']` for now (viewer is added in T022). Load `GET /company`; company admin can save name, time zone (options from `GET /time-zones`), BIN, contact person, phone, email as one form; 422 per field. Register the route in `resources/js/router/index.ts`. Header link for company admin to `/company` in `resources/js/components/AppHeader.vue` (viewer link in T023). Copy through `t()` in both locale files. Test first in `resources/js/pages/__tests__/CompanyProfilePage.spec.ts` (edit save, 422) and extend `resources/js/router/__tests__/router.spec.ts` (company admin stays on `/company`; super admin on `/company` goes to `/`). (depends on T008, T016)

**Checkpoint**: US4 works on its own: own-company profile GET/PATCH and time zone history.

---

## Phase 7: User Story 5 - Company admin changes working day settings without changing past days (Priority: P2)

**Goal**: A company admin replaces the current working day settings. The new set is the latest row immediately (US5-1). Calculations for today and earlier days still see the previous row until next local midnight (FR-016, SC-003). This feature does not compute late arrivals or hours worked (research R10).

**Independent Test**: Record the default settings. Change start time and break deduction. Confirm `CompanySchedule::workingDaySettingsAt` for an instant still today returns the previous version, and an instant on or after `applies_from` returns the new one.

- [x] T018 [US5] Implement `GET /api/v1/company/working-day-settings` behind `role:company_admin,viewer` (FR-013, FR-019). `app/Modules/Companies/Actions/ShowWorkingDaySettingsAction.php` reads the latest settings version (highest `id`). `app/Http/Controllers/Company/ShowWorkingDaySettingsController.php`. `app/Http/Resources/WorkingDaySettingsResource.php` `{ start_time, end_time, working_days, break_duration_minutes, break_deducted, lateness_grace_minutes }` (`HH:MM`, unique weekday identifiers). Route; `public/swagger.yaml`. Update `tests/Feature/Modules/Identity/RoleAccessTest.php` so viewer `GET` of this path succeeds; PATCH stays 403 until T019. Test first in `tests/Feature/Modules/Companies/ShowWorkingDaySettingsTest.php`: Acme admin 200 with FR-014 defaults for a factory company; super admin 403. (depends on T005, T007, T015)
- [x] T019 [US5] Implement `PATCH /api/v1/company/working-day-settings` behind `role:company_admin` (US5 scenarios 1–5; FR-015–FR-018; research R3, R4, R16). `app/Modules/Companies/Data/UpdateWorkingDaySettingsData.php`: `startTime`, `endTime`, `workingDays` (`list<WeekDay>`), `breakDurationMinutes`, `latenessGraceMinutes`, `breakDeducted` (full document; all six fields required). `app/Http/Requests/UpdateWorkingDaySettingsRequest.php`: `start_time`/`end_time` `date_format:H:i`; `working_days` required array of unique constraints.md identifiers, min 1; `break_duration_minutes` / `lateness_grace_minutes` required integers; `break_deducted` required boolean; `WorkingDaySettingsRangeRule` for end-after-start, at least one weekday, and 0 ≤ break/grace < working day length. `app/Modules/Companies/Actions/UpdateWorkingDaySettingsAction.php`: if the payload equals the latest row, insert nothing; otherwise insert with `applies_from` = next local midnight in the company's time zone at `now()` (same lookup as FR-012). No extra lock (research R16). Return latest settings. `app/Http/Controllers/Company/UpdateWorkingDaySettingsController.php` returns `WorkingDaySettingsResource`. Viewer and super admin → 403. `public/swagger.yaml`. Test first in `tests/Feature/Modules/Companies/UpdateWorkingDaySettingsTest.php`: valid PATCH becomes latest settings and GET shows them immediately; lookup for today (and earlier) still returns the previous version; lookup at/after `applies_from` returns the new one; end not after start / no weekdays / break or grace out of range → 422, no new row; identical PATCH → no new version row; two saves before midnight → highest `id` applies from that midnight; Acme admin reading Globex via an id-bearing admin path → 403. (depends on T006, T007, T018)
- [x] T020 [US5] Add working-day-settings load and save to `resources/js/api/companyProfile.ts` and `resources/js/pages/CompanyProfilePage.vue` as a second form (contracts/spa-routes.md): start, end, weekdays, break duration, break deducted, grace; 422 per field. Copy through `t()` in both locale files. Test first in `resources/js/pages/__tests__/CompanyProfilePage.spec.ts`: settings save and validation. (depends on T017, T019)

**Checkpoint**: US5 works on its own. Past-day lookup is unchanged after a save.

---

## Phase 8: User Story 6 - Viewer sees company details and settings read-only (Priority: P3)

**Goal**: A viewer GET-succeeds on own-company profile and settings, sees every value, and has no edit option. PATCH is 403. Another company's records are refused without existence leakage. Super admin stays 403 on `/company/*`.

**Independent Test**: As a viewer, open the company profile and settings and confirm all values are shown; attempt to change any value and confirm it is refused.

- [x] T021 [US6] Finish HTTP viewer and isolation proofs (US6 scenarios 1–3; FR-019, FR-021, SC-004, SC-005; research R9). Extend `tests/Feature/Modules/Companies/ShowCompanyProfileTest.php` and `ShowWorkingDaySettingsTest.php`: viewer GET profile and settings → 200, all fields; viewer PATCH either → 403, nothing changes; viewer GET Globex admin routes → 403; viewer still 403 on `/company/users` and invitations. Confirm `tests/Feature/Modules/Identity/RoleAccessTest.php`: viewer allowed only on the two GET own-company routes; every other `/company/*` method (PATCH, users, invitations) and every `/admin/*` route stays 403; super admin 403 on every `/company/*`. Change application code only if a test exposes a gap. (depends on T016, T019)
- [x] T022 [US6] Allow a viewer on `/company` in `resources/js/router/index.ts` (`meta.roles = ['company_admin', 'viewer']`). `resources/js/pages/CompanyProfilePage.vue`: viewer sees the same values with no edit controls; a viewer PATCH is not offered. Test first in `resources/js/pages/__tests__/CompanyProfilePage.spec.ts` (no save controls for viewer) and `resources/js/router/__tests__/router.spec.ts` (viewer on `/company` stays; viewer on `/company/users` still goes to `/`; viewer on `/companies` goes to `/`; super admin on `/company` goes to `/`). (depends on T017, T020, T021)
- [x] T023 [US6] Add role-filtered header links on `resources/js/components/AppHeader.vue` (contracts/spa-routes.md, plan §Assumptions): super admin → `/companies`; company admin → `/company/users` and `/company`; viewer → `/company`. Copy through `t()` in both locale files. Test first in `resources/js/components/__tests__/AppHeader.spec.ts`. (depends on T022)

**Checkpoint**: All user stories work on their own.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Checks that span several stories.

- [x] T024 Prove mutate-then-deactivate retention (US3 independent test after US4/US5; SC-006, edge case "A company is deactivated and reactivated"). In `tests/Feature/Modules/Companies/DeactivateCompanyTest.php` (or a dedicated file): as Acme admin, change a detail and a settings field, then as super admin deactivate and reactivate; details, current settings, and both version table rows (ids, payloads, `applies_from`) are unchanged. Change application code only if a test exposes a gap. (depends on T013, T016, T019)
- [x] T025 [P] Align `public/swagger.yaml` with [contracts/http-api.md](./contracts/http-api.md) for every 002 path, body, query, and error status (`GET/POST /admin/companies` search and create extras, deactivate/reactivate, 405 DELETE, `GET/PATCH /company`, `GET/PATCH /company/working-day-settings`, `GET /time-zones`). Keep `tests/Feature/OpenApiDocumentTest.php` and `tests/Feature/LocalizationParityTest.php` green (locale files still have identical key sets). No new PII logs. Run the targeted commands in [quickstart.md](./quickstart.md) §Automated gates. (depends on T009, T011, T016, T019, T008)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies. T002 runs in parallel with T001.
- **Foundational (Phase 2)**: Depends on Setup. Blocks every user story. T006 can start once T001 and T005 exist; T007 needs T004 and T005.
- **US1 (Phase 3)**: Depends on Foundational. T008 is parallel with T009; T010 needs both.
- **US2 (Phase 4)**: Depends on Foundational (`name_normalized`, `bin` on `CompanySummary`). Does not need US1 if factories set BIN; T011 still benefits from T002 fixtures.
- **US3 (Phase 5)**: Depends on Foundational version rows and US2 list `bin`. Full mutate-then-deactivate proof waits for T024.
- **US4 (Phase 6)**: Depends on Foundational `CompanySchedule` and T008 time-zone list. Does not need US2 search.
- **US5 (Phase 7)**: Depends on Foundational and US4's own-company route group / RoleAccessTest update (T015).
- **US6 (Phase 8)**: Depends on US4 and US5 GET/PATCH routes and `CompanyProfilePage.vue`.
- **Polish (Phase 9)**: Depends on US3 + US4 + US5 for T024; swagger/locales after the endpoint tasks.

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational. MVP.
- **User Story 2 (P1)**: Can start after Foundational. Independently testable with factory BIN/name.
- **User Story 3 (P1)**: Can start after Foundational. Independently testable with factory data; T024 completes the spec's mutate-then-deactivate path.
- **User Story 4 (P2)**: Can start after Foundational. Integrates with US1's `GET /time-zones`.
- **User Story 5 (P2)**: Can start after T015 (own-company routes and RoleAccessTest exception for GET `/company`).
- **User Story 6 (P3)**: Can start after US4 and US5 HTTP + page exist.

### Within Each User Story

- Tests MUST be written and FAIL before implementation.
- Models before services (Phase 2).
- Services before Actions.
- Actions before endpoints.
- Endpoints before the frontend that calls them.
- Story complete before moving to the next priority unless a later story only adds a proof (T024).

### Parallel Opportunities

- Phase 1: T002 beside T001.
- Phase 2: T005 `[P]` beside T004 once T003 is done; T006 after T001 and T005.
- Phase 3: T008 beside T009.
- Phase 5: T014 beside T013 once T012 is done.
- After Phase 2, US1 (T008–T010) and US2 (T011–T012) can proceed in parallel.
- Phase 9: T025 beside T024 once endpoints exist.

---

## Parallel Example: User Story 1

```bash
# Once Phase 2 is done:
Task: "T008 GET /time-zones in app/Http/Controllers/ListTimeZonesController.php"
Task: "T009 Extend POST /admin/companies in app/Modules/Companies/Actions/CreateCompanyAction.php"
```

## Parallel Example: Foundational

```bash
# Once T003 is done:
Task: "T004 Company and version models in app/Modules/Companies/Models/"
Task: "T005 WeekDay and PublicApi views in app/Modules/Companies/PublicApi/"

# Once T001 and T005 are done:
Task: "T006 BinRules, PhoneRules, OptionalEmailRules, WorkingDaySettingsRangeRule"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories).
3. Complete Phase 3: User Story 1.
4. **STOP and VALIDATE**: create with defaults and with full details; 422 on bad fields; admin/viewer cannot create.

### Incremental Delivery

1. Setup + Foundational → schema, versions, `CompanySchedule`.
2. US1 → create with details and settings snapshot on 201. MVP.
3. US2 → searchable list with BIN.
4. US3 → deactivate/reactivate keep data; no delete.
5. US4 → company admin profile and time zone history.
6. US5 → working day settings history; past-day lookup unchanged.
7. US6 → viewer read-only and header navigation.
8. Polish → mutate-then-deactivate proof and swagger/locale parity.

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together.
2. Once Foundational is done:
   - Developer A: User Story 1
   - Developer B: User Story 2 (then US3)
   - Developer C: User Story 4 (then US5, US6) after T008 if sharing the time-zone list
3. Stories complete and integrate independently except US5 needing T015 and US6 needing the profile page.

---

## Notes

- [P] tasks = different files, no dependencies between those [P] tasks.
- [Story] label maps the task to spec.md user stories US1–US6.
- Each user story should be independently completable and testable.
- Verify tests fail before implementing.
- Commit after each task or logical group only when a human asks.
- Stop at any checkpoint to validate the story independently.
- Avoid: vague tasks, same-file conflicts, inventing employees/terminals/timesheet calculations (research R10, spec assumptions).
- Late-arrival and hours-worked calculations belong to a later timesheet feature; T007 / T016 / T019 prove the version lookup those calculations will call.
