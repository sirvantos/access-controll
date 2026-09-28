# HTTP API Contract: Company Management

**Feature**: [../spec.md](../spec.md) · **Data**: [../data-model.md](../data-model.md) · **Limits**: [../constraints.md](../constraints.md)

Conventions, auth, error envelopes, pagination `links`/`meta`, and middleware aliases are those of [feature 001 http-api.md](../../001-user-authentication-and-roles/contracts/http-api.md). This file records the additive and changed company routes. The implementation mirrors them into `public/swagger.yaml`.

## Resources

| Resource | Shape |
|----------|-------|
| `CompanyResource` (list / deactivate / reactivate) | `{ id, name, bin, is_active, awaiting_first_admin, created_at }` — `bin` is `null` when empty; `created_at` ISO-8601 UTC |
| `CreatedCompanyResource` (`201` create) | `{ id, name, time_zone, bin, contact_person, phone, email, is_active, awaiting_first_admin, created_at, working_day_settings }` |
| `CompanyProfileResource` | `{ id, name, time_zone, bin, contact_person, phone, email }` |
| `WorkingDaySettingsResource` | `{ start_time, end_time, working_days, break_duration_minutes, break_deducted, lateness_grace_minutes }` |
| `TimeZoneIdentifiersResource` | `{ identifiers: string[] }` (IANA identifiers, sort order of `DateTimeZone::listIdentifiers()`) |

`working_day_settings` / `WorkingDaySettingsResource`:

- `start_time` / `end_time`: `HH:MM`
- `working_days`: array of `monday`…`sunday` (constraints.md), unique, at least one
- `break_duration_minutes` / `lateness_grace_minutes`: integers
- `break_deducted`: boolean

Optional strings in responses are JSON `null` when empty.

## Changed super-admin routes (`role:super_admin`)

| Method & path | Body / query | Success | Errors | Req. |
|---------------|--------------|---------|--------|------|
| `GET /admin/companies` | `page` (optional), `search` (optional, ≤255 characters) | `200` paginated `CompanyResource`. Search, when present, is trimmed and matched case-insensitively against part of `name` or part of `bin`, across the whole list then paginated (15 per page, order by `id`). An empty match is `200` with `data: []`. | `422` if `search` exceeds 255 characters | FR-005, FR-006 |
| `POST /admin/companies` | `name` (required), `first_admin_email` (required), `time_zone` (optional, default `Asia/Almaty`), `bin`, `contact_person`, `phone`, `email` (optional) | `201 CreatedCompanyResource` with default working day settings (FR-014). Company + first versions + first-admin invitation in one transaction; email queued after commit. | `422 errors.first_admin_email` when the email is already registered (no company left). `422` per invalid field (unknown `time_zone`, bad BIN/phone/email/name). | FR-001–FR-004 |
| `POST /admin/companies/{company}/deactivate` | none | `200 CompanyResource` (idempotent). Details, settings, and both histories unchanged. | `404` | FR-007, FR-009 |
| `POST /admin/companies/{company}/reactivate` | none | `200 CompanyResource` (idempotent). Same data as before deactivation. | `404` | FR-007, FR-009 |
| `DELETE /admin/companies/{company}` | not defined | `405` | none | FR-008 |

Unchanged from feature 001: first-admin invitation routes under `/admin/companies/{company}/invitations`.

A super admin `GET` or `PATCH` of `/company` or `/company/working-day-settings` returns `403` (FR-020).

## Own-company profile (`auth:sanctum`, `current-session`)

Scoped to `actorCompanyId()`. There is no company id in the path (research R8).

| Method & path | Roles | Body | Success | Errors | Req. |
|---------------|-------|------|---------|--------|------|
| `GET /company` | `company_admin`, `viewer` | none | `200 CompanyProfileResource` (latest saved name, time zone, details) | `403` other roles | FR-019 |
| `PATCH /company` | `company_admin` | any of `name`, `time_zone`, `bin`, `contact_person`, `phone`, `email`. Omitted `name` / `time_zone` stay unchanged. Empty `bin` / `contact_person` / `phone` / `email` clear that field. | `200 CompanyProfileResource`. A time zone change inserts a version that applies from the next local midnight (FR-012). Identical values insert nothing. | `403` viewer and super admin. `422` empty name, unknown time zone, or invalid optional format. | FR-010, FR-012, FR-019 |
| `GET /company/working-day-settings` | `company_admin`, `viewer` | none | `200 WorkingDaySettingsResource` (latest saved settings) | `403` other roles | FR-019 |
| `PATCH /company/working-day-settings` | `company_admin` | all of `start_time`, `end_time`, `working_days`, `break_duration_minutes`, `break_deducted`, `lateness_grace_minutes` | `200 WorkingDaySettingsResource`. Inserts a version that applies from the next local midnight (FR-016) unless identical to the latest row. | `403` viewer and super admin. `422` when end is not after start, no working days, or break/grace out of range. | FR-015, FR-016, FR-018 |
| `GET /time-zones` | `super_admin`, `company_admin` | none | `200 TimeZoneIdentifiersResource` | `403` viewer | FR-002 |

A company admin or viewer calling any `/admin/companies*` route remains `403` and does not reveal whether a company id exists (FR-021). `DELETE /company` is not defined (`405`).

## Field validation (422 `errors.{field}`)

Limits and formats are [constraints.md](../constraints.md). Messages come from `lang/{en,ru}/validation.php` (and company-specific keys under `lang/{en,ru}/companies.php` when a built-in message is not enough). Both locales are required.

| Field | Rule |
|-------|------|
| `name` | required on create and when present on PATCH; 1–255 characters |
| `time_zone` | optional on create (default `Asia/Almaty`); when present, `timezone:all` |
| `bin` | optional; exactly 12 digits or empty |
| `contact_person` | optional; 1–255 characters or empty |
| `phone` | optional; 10–15 digits, optional leading `+`, optional spaces/hyphens/parentheses, max 32 characters, or empty |
| `email` | optional; email, max 255, or empty |
| `first_admin_email` | required on create; existing `EmailRules` |
| `search` | optional query; max 255 |
| `start_time`, `end_time` | required on settings PATCH; `date_format:H:i`; end after start |
| `working_days` | required array of unique weekday identifiers; min 1 |
| `break_duration_minutes`, `lateness_grace_minutes` | required integers; 0 ≤ n < working day length |
| `break_deducted` | required boolean |
