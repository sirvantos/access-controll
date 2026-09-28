# Quickstart & Validation: Company Management

**Feature**: [spec.md](./spec.md) · **Contracts**: [contracts/http-api.md](./contracts/http-api.md), [contracts/spa-routes.md](./contracts/spa-routes.md) · **Limits**: [constraints.md](./constraints.md)

This is a validation guide. Implementation detail belongs in `tasks.md`.

## Prerequisites

- Feature 001 is implemented (sign-in, roles, `POST /admin/companies` with first-admin invitation, deactivate/reactivate).
- PHP 8.4, Composer, Node `^20.19 || >=22.12`.
- `composer install`, `npm install`, `.env` from `.env.example`, `php artisan key:generate`, `php artisan migrate`.
- Mail driver `log` locally; `php artisan queue:work` for invitation email outside tests.

## Automated gates (Definition of Done)

```bash
make verify
```

Targeted runs while iterating:

```bash
php artisan test --compact tests/Feature/Modules/Companies
php artisan test --compact tests/Unit/Modules/Companies
php artisan test --compact tests/Feature/Modules/Identity/RoleAccessTest.php
npm run test:run
```

Test conventions (feature 001 research R13, frozen-clock rule):

- Stateful `Referer` via `tests/Support/Identity`.
- `actingAs($user, 'web')`, `Notification::fake()` on create, `Carbon::setTestNow()` before fixtures for FR-012/FR-016.
- Messages compared with `__()` under `en` and `ru`.
- Concrete names, BINs, and times (reuse `acmeCompany()` / `globexCompany()`; add `tests/Support/Companies` payloads for settings and a 12-digit BIN).

## Scenario map

Each acceptance scenario in `spec.md` needs an automated test.

| Story | Test file | Key checks |
|-------|-----------|------------|
| US1 create | `tests/Feature/Modules/Companies/CreateCompanyTest.php` (extend) | Super admin create with only name + first-admin email: 201, active, `time_zone` `Asia/Almaty`, FR-014 settings in `working_day_settings`. Create with another zone and all optional details: all saved on 201. Missing name / unknown zone / bad BIN-phone-email: 422, no row. Company admin and viewer POST: 403. |
| US2 list + search | `tests/Feature/Modules/Companies/ListCompaniesTest.php` (extend) | Super admin sees active and deactivated with name, `bin`, state, `created_at`. Search `"stroy"` matches `"Alma Stroy"`; search `"4567"` matches BIN `123456789012`; no match returns empty `data` (SPA shows the empty copy). Page size 15; search applies before pagination. Company admin and viewer GET list: 403. |
| US3 deactivate | `tests/Feature/Modules/Companies/DeactivateCompanyTest.php`, `ReactivateCompanyTest.php` (extend) | Deactivate then reactivate: details, current settings, and both version tables unchanged. Idempotent deactivate/reactivate: 200, no error. `DELETE /admin/companies/{id}`: 405. Company admin/viewer deactivate: 403. Feature 001 session-end behaviour on deactivate stays. |
| US4 profile | `tests/Feature/Modules/Companies/UpdateCompanyProfileTest.php`, `ShowCompanyProfileTest.php` | Acme admin PATCH valid fields and clear an optional: 200 and GET shows it. Empty name / bad format: 422, unchanged. Time zone change: latest row is the new identifier; `CompanySchedule::timeZoneIdentifierAt` for an instant still today returns the previous identifier; after `applies_from` it returns the new one. Acme admin GET/PATCH Globex via `/admin/companies/{globex}`: 403. Super admin GET `/company`: 403. |
| US5 settings | `tests/Feature/Modules/Companies/UpdateWorkingDaySettingsTest.php`, `tests/Unit/Modules/Companies/CompanyScheduleServiceTest.php` | Valid PATCH becomes latest settings. Lookup for today (and earlier) still returns the previous version; lookup at/after `applies_from` returns the new one (SC-003 mechanism, research R10). End not after start / no weekdays / break or grace out of range: 422. Acme admin reading Globex settings: 403. Identical PATCH: no new version row. Two saves before midnight: highest `id` applies from that midnight. |
| US6 viewer | `tests/Feature/Modules/Companies/ShowCompanyProfileTest.php`, `ShowWorkingDaySettingsTest.php`, `tests/Feature/Modules/Identity/RoleAccessTest.php` | Viewer GET profile and settings: 200, all fields. Viewer PATCH either: 403, nothing changes. Viewer GET Globex admin routes: 403. Viewer still 403 on `/company/users` and invitations. |
| Frontend | `resources/js/pages/__tests__/CompaniesPage.spec.ts`, `CompanyProfilePage.spec.ts`, `resources/js/router/__tests__/router.spec.ts`, `resources/js/components/__tests__/AppHeader.spec.ts` | Search empty copy via `t()`. Create fields and 422. BIN column. Profile edit vs viewer read-only (no edit controls). Router: viewer allowed on `/company`, refused on `/companies` and `/company/users`; super admin refused on `/company`. |

`CompanyScheduleServiceTest` freezes time at a whole second, e.g. `2026-01-15 12:00:00` UTC, with company zone `Asia/Almaty`, and asserts the stored `applies_from` equals Almaty `2026-01-16 00:00:00`.

## Manual smoke run

1. Sign in as the super admin. `/companies` lists existing companies including BIN when set.
2. Create a company with only name and first-admin email. Confirm the 201 (and the new row) uses `Asia/Almaty` and 09:00–18:00 Monday–Friday, 60-minute deducted break, 0 grace.
3. Create a second company with BIN `123456789012` and time zone `Europe/Moscow`. Search `4567` and `stroy` (use a name that contains that substring). Confirm pagination still works with the filter.
4. Deactivate and reactivate the second company; BIN and name are unchanged. Confirm there is no delete control.
5. Accept the first company's admin invitation, sign in, open `/company`, change the grace period, and save. Confirm the page shows the new grace immediately.
6. (Optional while timesheets do not exist) In tinker or a unit test, resolve settings for "now" and for tomorrow's `applies_from`; today's lookup must still be the defaults.
7. Invite a viewer, sign in as the viewer, open `/company`, confirm values are visible and there is no save control. Opening `/company/users` redirects home.
8. As the viewer, confirm `/companies` redirects home.
