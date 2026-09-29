# Quickstart & Validation: Data Isolation Between Companies

**Feature**: [spec.md](./spec.md) · **Contracts**: [contracts/http-api.md](./contracts/http-api.md), [contracts/spa-routes.md](./contracts/spa-routes.md), [contracts/isolation.md](./contracts/isolation.md), [contracts/terminal-events.md](./contracts/terminal-events.md) · **Limits**: [constraints.md](./constraints.md)

This is a validation guide. Implementation detail belongs in `tasks.md`.

## Prerequisites

- Features 001 and 002 are implemented (sign-in, roles, companies, own-company profile/settings, Acme/Globex test helpers).
- PHP 8.4, Composer, Node `^20.19 || >=22.12`.
- `composer install`, `npm install`, `.env` from `.env.example`, `php artisan key:generate`, `php artisan migrate`.
- `Storage::fake()` (or the private disk fake) in media tests.

## Automated gates (Definition of Done)

```bash
make verify
```

Targeted runs while iterating:

```bash
php artisan test --compact tests/Feature/Modules/Tenancy
php artisan test --compact tests/Unit/Modules/Tenancy
php artisan test --compact tests/Feature/Modules/Identity/RoleAccessTest.php
php artisan test --compact tests/Feature/Modules/Identity/TenantScopingTest.php
php artisan test --compact tests/Feature/Modules/Companies/ShowCompanyProfileTest.php
npm run test:run
```

Test conventions (feature 001 research R13):

- Stateful `Referer` via `tests/Support/Identity`.
- Super admin company-data calls: `POST /api/v1/admin/selected-company` then header `X-Company-Context`.
- `actingAs($user, 'web')`, `Storage::fake()`, `Log::fake()` silence proofs on new `ShouldntReport` exceptions.
- Compare 404 bodies of a Globex id and an unused id with `assertExactJson` (or equivalent).
- Messages compared with `__()` under `en` and `ru`.
- Concrete overlapping seeds: employee number `17`, name `Aigerim Sarsenova` only on Globex for the empty-search case.

## Scenario map

Each acceptance scenario in `spec.md` needs an automated test.

| Story | Test file | Key checks |
|-------|-----------|------------|
| US1 lists, search, counters, reports, exports | `tests/Feature/Modules/Tenancy/CompanyIsolationScanTest.php`, `TenantScopingTest.php` (keep) | Acme admin: every `/api/v1/company*` list contains only Acme. Search `Sarsenova` is empty. Employee/user counts match Acme. Export/report media of Globex is not in Acme responses. Probe list included (SC-008). Product employee/report screens that do not exist yet are covered by the scan + probe, not by invented public list APIs. |
| US2 not found | `CompanyIsolationScanTest.php`, `TenantScopingTest.php` | Globex user/invitation/media/probe ids: 404 JSON identical to unused ids; Globex unchanged. Cross-company reference refused as not found. |
| US3 create assignment | `tests/Feature/Modules/Tenancy/CompanyAssignmentTest.php` plus existing invite tests | New probe/employee/invitation belongs to Acme. Body `company_id` of Globex is ignored or unused; Globex unchanged. Context missing → save refused. |
| US4 media | `tests/Feature/Modules/Tenancy/CompanyMediaTest.php` | Guest GET media 404. Globex user GET Acme media 404 (same as unused UUID). After sign-out, copied UUID 404. Viewer GET employee photo 403; viewer GET event snapshot 200 when in company. Super admin without selection 409; with selection, same as company admin. |
| US5 events and numbers | `tests/Feature/Modules/Tenancy/RecordTerminalEventTest.php`, `CreateEmployeeTest.php` | Acme terminal + `17` attaches only Acme employee `17`. Globex `17` unchanged. Unknown terminal: no row. Duplicate number in Acme: `EmployeeNumberTakenException` / HTTP 422 if exposed later, no Globex leak. Duplicate vs Globex only: Acme create succeeds. |
| US6 super admin selection | `tests/Feature/Modules/Tenancy/SelectCompanyTest.php`, `RoleAccessTest.php` (update), Companies profile tests | No selection: `/company` 409 `company_not_selected`, no company data. Select Acme: name in `SelectedCompanyResource`; lists are Acme-only; Globex id 404. Switch to Globex: only Globex. Select and PATCH profile: two action rows (`selected_company`, `changed_company_data`); GET media and download export add none. Deactivated company: select and PATCH allowed; company stays deactivated. Direct link without selection: 409, no data. |
| US7 default isolation | `CompanyIsolationScanTest.php`, `tests/Feature/Modules/Tenancy/IsolationProbeTest.php`, `tests/Unit/Modules/Tenancy/CompanyContextJobTest.php` | Probe model with only `BelongsToCompany` passes the two-company scan. Job `run(acme)` does not touch Globex. Named exceptions in isolation.md still work (company list without selection). |
| Frontend | `resources/js/composables/__tests__/useSelectedCompany.spec.ts`, `AppHeader.spec.ts`, `CompaniesPage.spec.ts`, `router.spec.ts`, `client.spec.ts` | Header shows selected name (`t()`). Two stored selections do not mix. Client sends `X-Company-Context` only for `super_admin`. Super admin without selection cannot stay on `/company`. `409` `company_not_selected` handled. |

`CompanyNotSelectedException` and `EmployeeNumberTakenException` each have a `Log::fake()` silence proof.

## Manual smoke run

1. Sign in as super admin. Header prompts to select a company. `/company` redirects to `/companies`.
2. Select Acme. Header shows Acme. Open `/company` and `/company/users`; only Acme data. Open a Globex user id in the API: 404.
3. In another tab, select Globex. That tab's header shows Globex; the first tab still shows Acme. Neither list mixes rows.
4. Change Acme's profile as super admin. Confirm the company stays selected. Open an employee photo URL, sign out, paste the URL: no image.
5. As Acme admin (not super admin), confirm lists ignore a forged `X-Company-Context` for Globex.
6. Deactivate Acme. Super admin can still select it and edit. Acme's company admin cannot sign in.
