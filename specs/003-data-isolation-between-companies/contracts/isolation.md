# Isolation Contract: Named Exceptions, Scan, and Probe

**Feature**: [../spec.md](../spec.md) · **Plan**: [../plan.md](../plan.md)

FR-024: only these functions may run `CompanyContext::withoutIsolation`. A new cross-company query that is not on this list is a defect.

## Named exceptions (super admin / identity lookup)

| Identifier | Function | Why |
|------------|----------|-----|
| `admin.list_companies` | `GET /admin/companies` | FR-015 |
| `admin.create_company` | `POST /admin/companies` | feature 002 |
| `admin.deactivate_company` | `POST /admin/companies/{company}/deactivate` | feature 002; no selection required |
| `admin.reactivate_company` | `POST /admin/companies/{company}/reactivate` | feature 002 |
| `admin.first_admin_invitations` | `/admin/companies/{company}/invitations*` | feature 001 FR-020 remainder; scoped to path `{company}`, still `awaiting-first-admin` |
| `admin.select_company` | `POST /admin/selected-company` | must resolve the company by id without already having context |
| `identity.sign_in` | user lookup by email | email is global (001 FR-007) |
| `identity.email_uniqueness` | invite / accept / create-super-admin email checks | email is unique across companies |
| `identity.create_super_admin` | console command | user with `company_id` null |
| `identity.password_reset` | broker lookup by email | global email |
| `identity.end_company_sessions` | listener may load users of one company id from the event; prefer `CompanyContext::run($event->companyId)` instead of `withoutIsolation` when possible | |
| `identity.session_user` | session auth User retrieval (`retrieveById`) | session loads the user before `company-context` |
| `companies.directory_before_context` | `CompanyDirectoryService::exists` / `isActive` | `current-session` reads Company before context exists |
| `identity.show_invitation` | `ShowInvitationAction` / `InvitationTokenService::findByToken` | guest invitation preview |
| `identity.accept_invitation` | `AcceptInvitationAction` | keep the invitation `company_id` on the new user |
| `tenancy.record_terminal_event.load_terminal` | load terminal by id before context is bound | FR-013; then `run(terminal.companyId)` |
| `time_zones.list` | `GET /time-zones` | not company data |
| `auth.me_sign_out` | `GET /me`, `POST /auth/sign-out` | not company records |

Company-data routes (`/company`, `/company/*`) are **never** exceptions.

## Isolation scan (FR-026)

`CompanyIsolationScanTest` seeds Acme and Globex with overlapping employee names, employee number `17`, terminals of the same name, and media.

For every materialized `/api/v1/company*` route (except HEAD), as Acme admin:

1. List/search/filter/page responses contain no Globex ids, names, or emails.
2. Counters derived from those responses match Acme only.
3. Open/change/delete with a Globex id returns **byte-for-byte the same JSON** as the same method on an unused id (FR-006), and Globex rows are unchanged.
4. `GET /company/media/{globex_public_id}` matches `GET /company/media/{unused-uuid}`.

Skip routes in the named-exception table.

## SC-008 probe

A test-only `BelongsToCompany` model with no extra scopes, plus `GET`/`POST` `/_test/company/isolation-probes` behind `auth:sanctum`, `current-session`, `role:company_admin,super_admin`, `company-context`. The scan includes these routes. Production swagger and `routes/api.php` do not.

## Background work (FR-025)

A queued job or `RecordTerminalEventAction` that is started for Acme calls `CompanyContext::run(acmeId, …)` and must not read or write Globex. A job that does not call `run` and then touches `BelongsToCompany` fails (context required).
