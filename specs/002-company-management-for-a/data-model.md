# Data Model: Company Management

**Feature**: [spec.md](./spec.md) · **Limits**: [constraints.md](./constraints.md) · **Decisions**: [research.md](./research.md)

Every model defines `casts()` for every column. No row in `companies` or in the version tables is ever deleted (FR-008, R4). Optional company details that are empty are stored as `NULL` (R12).

## Module ownership

| Table | Owning module | Model |
|-------|---------------|-------|
| `companies` | Companies | `App\Modules\Companies\Models\Company` (existing; columns added) |
| `company_time_zone_versions` | Companies | `App\Modules\Companies\Models\CompanyTimeZoneVersion` |
| `company_working_day_setting_versions` | Companies | `App\Modules\Companies\Models\CompanyWorkingDaySettingVersion` |

Identity tables are unchanged. `users.company_id` and `invitations.company_id` remain schema foreign keys only. Companies still has no Eloquent relation to Identity models.

Same-module relations (allowed): `Company` `hasMany` both version models. Version models `belongsTo` `Company`.

## Company (Companies) — additive columns

Existing columns stay: `id`, `name` string(255) required 1–255 characters, `deactivated_at` nullable timestamp (null means active), `created_at`, `updated_at`.

| Column | Type | Rules |
|--------|------|-------|
| `bin` | string(12), nullable | optional; exactly 12 digits when set |
| `contact_person` | string(255), nullable | optional; 1 to 255 characters when set |
| `phone` | string(32), nullable | optional; constraints.md phone rule when set |
| `email` | string(255), nullable | optional; valid email, lowercased, 255 characters at most |
| `name_normalized` | string(255) | `mb_strtolower` of `name`; maintained on create and on name change; used only for FR-006 search |

State is unchanged from feature 001: `active` ⇄ `deactivated`, both transitions idempotent, deactivation still dispatches `CompanyDeactivated` inside the same transaction. Deactivation does not alter details, versions, or `name_normalized`.

Index: ordinary btree on `name_normalized` and on `bin` (nullable) for the 1,000-company list (SC-002). Leading-wildcard `LIKE` will still scan at this scale.

## CompanyTimeZoneVersion (Companies)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `company_id` | FK → `companies.id` | required |
| `time_zone` | string(64) | IANA identifier; validated with `timezone:all` |
| `applies_from` | timestamp | UTC instant from which this identifier applies (R2) |
| `created_at` | timestamp | no `updated_at` (append-only) |

Index: (`company_id`, `applies_from`, `id`).

A company always has at least one row (created with the company, or backfilled, R14). Profile time zone = latest `id`. Time zone at instant T = latest `id` among rows with `applies_from <= T`.

## CompanyWorkingDaySettingVersion (Companies)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint PK | |
| `company_id` | FK → `companies.id` | required |
| `start_time` | time | `HH:MM` 00:00–23:59 |
| `end_time` | time | later than `start_time` on the same day |
| `working_days` | json | unique list of `WeekDay` values; at least one |
| `break_duration_minutes` | unsigned smallint | 0 ≤ value < working day length in minutes |
| `break_deducted` | boolean | |
| `lateness_grace_minutes` | unsigned smallint | 0 ≤ value < working day length in minutes |
| `applies_from` | timestamp | UTC instant from which this set applies (R2) |
| `created_at` | timestamp | no `updated_at` |

Index: (`company_id`, `applies_from`, `id`).

Defaults for the first row (FR-014): start 09:00, end 18:00, `monday`–`friday`, break 60, `break_deducted` true, grace 0.

Working day length in minutes = minutes from `start_time` to `end_time` (R17).

## Enums

| Type | Values |
|------|--------|
| `Companies\PublicApi\WeekDay` | `Monday = 'monday'`, …, `Sunday = 'sunday'` (TitleCase keys, `constraints.md` identifiers as values) |

## State transitions

### Time zone versions

- **Create company**: insert version (`time_zone` from the request or `Asia/Almaty`, `applies_from` = start of the creation local day in that zone).
- **Change (company admin)**: if the identifier equals the latest row, no insert. Otherwise insert with `applies_from` = next local midnight in the zone that is in effect at the save instant (that zone is the schedule lookup at `now()`, which is still the previous identifier until that midnight).
- **Deactivate / reactivate**: no version change.

### Working day setting versions

Same shape as time zone versions, with the FR-014 payload on create and the next-midnight rule on change (FR-016). The "zone in effect at save" for settings is the company's time zone at `now()` (the same lookup as FR-012).

## PublicApi types

| Type | Kind | Members |
|------|------|---------|
| `Companies\PublicApi\CompanyDirectory` | interface (existing) | unchanged: `exists`, `isActive` |
| `Companies\PublicApi\CompanySummary` | readonly DTO (existing, additive) | `id`, `name`, `bin` (`?string`), `isActive`, `awaitingFirstAdmin`, `createdAt` |
| `Companies\PublicApi\CompanyDeactivated` | event (existing) | unchanged |
| `Companies\PublicApi\WeekDay` | backed enum | see Enums |
| `Companies\PublicApi\WorkingDaySettingsView` | readonly DTO | `startTime` (`CarbonInterface` time-of-day), `endTime`, `workingDays` (`list<WeekDay>`), `breakDurationMinutes`, `breakDeducted`, `latenessGraceMinutes` |
| `Companies\PublicApi\CompanyProfileView` | readonly DTO | `id`, `name`, `timeZone`, `bin`, `contactPerson`, `phone`, `email` |
| `Companies\PublicApi\CreatedCompanyView` | readonly DTO | `CompanyProfileView` fields plus `isActive`, `awaitingFirstAdmin`, `createdAt`, `workingDaySettings` (`WorkingDaySettingsView`) |
| `Companies\PublicApi\CompanySchedule` | interface | `timeZoneIdentifierAt(int $companyId, CarbonInterface $instant): string`; `workingDaySettingsAt(int $companyId, CarbonInterface $instant): WorkingDaySettingsView` |

`CompanySchedule` is implemented by `Companies\Services\CompanyScheduleService` and bound in `CompaniesServiceProvider`. Future timesheet code imports only this interface and the view/enum types.

## Internal Data (Spatie Laravel Data)

| Type | Fields |
|------|--------|
| `CreateCompanyData` (existing, additive) | `name`, `firstAdminEmail`, `timeZone` (string, default `Asia/Almaty`), `bin`, `contactPerson`, `phone`, `email` (each `?string`) |
| `UpdateCompanyProfileData` | `name` (`?string`), `timeZone` (`?string`), `bin`, `contactPerson`, `phone`, `email` (nullable optionals; omitted name/time zone stay unchanged) |
| `UpdateWorkingDaySettingsData` | `startTime`, `endTime`, `workingDays` (`list<WeekDay>`), `breakDurationMinutes`, `latenessGraceMinutes`, `breakDeducted` |

## Domain exceptions (`app/Exceptions`, `ShouldntReport`)

This feature adds none. Unknown company ids on super-admin deactivate/reactivate stay 404 as in feature 001. Validation failures stay 422. Role mismatches stay 403. There is no delete route (405). Identical saves and idempotent deactivate/reactivate are successes, not errors.

## Validation rule objects

| Rule | Purpose |
|------|---------|
| `App\Support\Validation\BinRules` | optional, exactly 12 digits |
| `App\Support\Validation\PhoneRules` | optional; constraints.md phone pattern and 32-character max |
| `App\Support\Validation\OptionalEmailRules` | optional; `email` + max 255 (reuse `EmailRules` limits, not required) |
| `App\Rules\Companies\WorkingDaySettingsRangeRule` | end after start; at least one weekday; break and grace in range (R17) |
| Laravel `timezone:all` | IANA identifier |

Named constants on the Form Requests / rules match `constraints.md` field names (`COMPANY_NAME_MAX_LENGTH` already exists; add `COMPANY_BIN_LENGTH = 12`, `COMPANY_PHONE_MAX_LENGTH = 32`, `COMPANY_CONTACT_PERSON_MAX_LENGTH = 255`, `DEFAULT_TIME_ZONE = 'Asia/Almaty'`, default times and break/grace as typed constants on the settings Action or a small `WorkingDaySettingDefaults` type in `Companies\Data`).
