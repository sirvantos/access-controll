# Research: Data Isolation Between Companies

**Feature**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Limits**: [constraints.md](./constraints.md)

Each entry: Decision · Rationale · Alternatives considered. All Technical Context unknowns are resolved here.

## R1. A Tenancy module owns isolation; product screens stay later

- **Decision**: Add `App\Modules\Tenancy`. It owns company-context PublicApi, company media serving, super-admin selection, super-admin action records, terminal-event matching, and the employee-number uniqueness rule. `BelongsToCompany` and `CompanyIsolationScope` live in `app/Support` with a Support company-context store that does not import Tenancy. Identity and Companies keep their tables, use that Support trait, and talk to Tenancy only through `Tenancy\PublicApi`. This feature does **not** add public employee, terminal, timesheet, report, or export product screens. Those remain later features, as in feature 001's quickstart note. It **does** persist the minimum company-owned rows those rules need (employee, terminal, access event, media) and a test-only probe model that uses `BelongsToCompany` and nothing else (SC-008).
- **Rationale**: FR-023/FR-026/SC-008 are about default isolation, not about building the rest of the CRM. Inventing employee-management REST and UI would go beyond this spec. The named entities in FR-001 still need a company owner, a uniqueness rule, and event matching in production code so later features inherit them. Identity and Companies may depend only on other modules' PublicApi plus Support and Exceptions (`deptrac.php` is not edited).
- **Alternatives**: (a) Stancl Tenancy / extra packages — constitution Prohibitions: a new dependency must be named; Eloquent scopes and middleware already do this. (b) Putting the trait in Tenancy `PublicApi` — PublicApi must not carry Eloquent scope machinery. (c) Identity/Companies importing Tenancy models — fails deptrac Identity/Companies layers. (d) Implementing full employee/terminal HTTP now — not specified.

## R2. Request-scoped company context, not a singleton

- **Decision**: `Tenancy\PublicApi\CompanyContext` is bound per request (container `scoped`, flushed for Octane). Middleware `EnsureCompanyContext` sets it through `CompanyContextService`, which reads and writes `App\Support\CompanyContextStore` (the store does not import Tenancy and MUST NOT name `App\Exceptions`). When the store is unbound it invokes a handler registered by `CompanyContextService`; that service throws `CompanyContextRequiredException`. Actions read the PublicApi type; the Eloquent global scope reads the Support store. They do not read `Request`, `Session`, or `Gate`. Queued work calls `CompanyContext::run(int $companyId, Closure $callback)` and must not read the HTTP context. A missing context on a `BelongsToCompany` query or save throws `CompanyContextRequiredException` (maps to refusing the save, FR-003 / FR-025) unless inside `withoutIsolation`.
- **Rationale**: Constitution Octane rules and Laravel's Octane docs: do not inject `Request` into a singleton. `Illuminate\Support\Facades\Context` may carry the id for the request; the PublicApi type is the only import other modules use.
- **Alternatives**: A static `$currentCompanyId` leaks across Octane requests. Session-only context breaks two browser tabs (spec edge case).

## R3. Super admin selection is per request (header) plus an explicit select

- **Decision**:
  - The SPA keeps `{ id, name }` in **`sessionStorage`** (per tab) and sends header `X-Company-Context: {id}` (constraints.md) on company-data requests.
  - `POST /api/v1/admin/selected-company` with `{ company_id }` is the explicit select (FR-016, FR-020). It records a selection row, adds that id to a **session allow-list**, and returns `{ id, name }`.
  - Company-data routes for a super admin require the header, and the id MUST be on that session's allow-list. Missing header or an id that was never selected this session → `409` `error_code = company_not_selected` (ask to select; not 404, which would mean "this record does not exist").
  - `DELETE /api/v1/admin/selected-company` is the clear control for this tab's client; the server does not need the allow-list emptied (the other tab may still use a company it selected). Omitting the header is enough to refuse company data.
  - Switching is another `POST` (logged). Company admins/viewers: the header is ignored; context is always their `actorCompanyId()` (FR-002).
- **Rationale**: Two tabs with different companies cannot share one session-stored "current company". An allow-list still forces an explicit select before data access, so FR-020's selection record is not skippable by only sending a header. `409` is the same family as `last_active_admin` (structured `error_code`), and it is distinguishable from `404` (FR-006).
- **Alternatives**: (a) Session-only current company — tabs overwrite each other. (b) Company id in the URL for `/company/*` — feature 002 R8 omitted ids so company users cannot probe; keep `/company` without an id and pass context in the header. (c) Header alone without POST — selections would not be recorded.

## R4. Isolation by default: Eloquent global scope on `BelongsToCompany`

- **Decision**: Models that are company records use trait `App\Support\BelongsToCompany` (or `#[ScopedBy(CompanyIsolationScope::class)]`). The trait and `App\Support\CompanyIsolationScope` read `App\Support\CompanyContextStore` and MUST NOT import Tenancy or name `App\Exceptions`. Unbound query or save goes through the store's registered handler; `CompanyContextService` throws `CompanyContextRequiredException`. The default scope column is `company_id`: the scope adds `where company_id = context.companyId()`, and `creating` sets `company_id` from context and overwrites any other company id in the attributes (FR-002). A model may declare another scope column. `Company` declares `id` (`where id = context.companyId()`). The `companies` table has no `company_id` column and does not gain one; creating a `Company` does not assign `company_id`. A save with no context is refused (FR-003) except inside `withoutIsolation`, which skips creating-assignment and the no-company refusal. A `User` may keep `company_id` null only when the role is `super_admin`. Every other company record still refuses a null company. `find` / route ids outside the company therefore 404 like a missing row (FR-006), including `ModelNotFoundException`. Named exceptions call `CompanyContext::withoutIsolation(Closure $callback)` from a **single registry** of cross-company functions (FR-024, contracts/isolation.md).
- **Rationale**: FR-023: a new model that only uses the trait is isolated. SC-008 is a test model that does exactly that. Per-Action `where('company_id', …)` is what exists today and will be missed on the next screen.
- **Alternatives**: (a) Keep manual `where` in every Action — fails FR-023. (b) Separate databases per company — out of scope and not in the spec.

## R5. What is in the scope, and the named exceptions

- **Decision**: In scope: `Invitation`, company-owned Identity user queries for company-data routes, `CompanyTimeZoneVersion`, `CompanyWorkingDaySettingVersion`, Tenancy employee/terminal/access-event/media, and every future `BelongsToCompany` model. `User` uses the trait so company user lists cannot see another company; **sign-in, email uniqueness, creating a super admin, session User retrieval, CompanyDirectory reads before context, and invitation show/accept** run `withoutIsolation` (email is unique across the whole service, feature 001 FR-007; accept keeps the invitation `company_id` on the new user). `Company` itself is scoped by its primary key `id` when a company context is bound (own-company profile: `where id = context.companyId()`) and listed `withoutIsolation` on the super-admin company list (FR-015). It does not use a `company_id` column. `SignInSecurityEvent` is not a company record (FR-001) and is not scoped. Super-admin action records are not company-data for company users; they are append-only logs keyed by `company_id` and are not writable through company routes.
- **Rationale**: The legal `withoutIsolation` identifiers are exactly the rows in contracts/isolation.md (FR-024), including feature 001 global email and sign-in lookups and terminal load before context exists. Everything else defaults to the current company.
- **Alternatives**: Scoping `User` with no exceptions would break sign-in and global email uniqueness.

## R6. Super admin on `/company/*` acts as company admin of the selected company

- **Decision**: Routes that are `role:company_admin` (and viewer GETs) also allow `super_admin`, then `EnsureCompanyContext` supplies the selected company. Authorization still matches a company admin of that company (FR-022), including last-active-admin (001 FR-037). Feature 001 FR-020 and feature 002 FR-020 no longer refuse those routes when a company is selected. Without a selection they return `409 company_not_selected`, not `403`. The first-admin invitation routes under `/admin/companies/{company}/invitations` stay as in 001 (named exception, no selection required, still `awaiting-first-admin`). `RoleAccessTest` is updated: super admin without header is 409 on `/company/*`; with a selected company, the same calls succeed or fail as for that company's admin.
- **Rationale**: Clarification Q1 = B. Deactivated companies remain selectable and mutable (Q3 = B). Company users of a deactivated company still cannot sign in (001 FR-006).
- **Alternatives**: A parallel `/admin/workspace/{company}/…` tree would duplicate every company route and still need the same scope.

## R7. Photos, snapshots, reports, and exports are private media

- **Decision**: Table `company_media` (`BelongsToCompany`): `public_id` UUID, `kind` enum (`employee_photo`, `event_snapshot`, `report`, `export`), storage path `{company_id}/{public_id}` on the existing **private** `local` disk (`storage/app/private`), with disk `serve` disabled for this use. `GET /api/v1/company/media/{public_id}` is the only way to read bytes: `auth:sanctum`, `current-session`, company context, and role-by-kind (feature 001: viewer may read events/reports; viewer must not read employee photos or management data). Guest and signed-out session return `401`. Signed-in other company and unknown UUID return the same `404` as a missing record (FR-006, FR-008, FR-009). No `storage:link`, no public disk, no temporary signed URLs that work after sign-out.
- **Rationale**: FR-009 forbids public, permanent, and guessable addresses and requires a signed-in session that is allowed to see the file. Sequential ids are guessable; UUID is not. Signed URLs that ignore the session would keep working after sign-out.
- **Alternatives**: (a) Files in `public/` or `/storage`. (b) Laravel `URL::temporarySignedRoute` without auth — fails the sign-out case.

## R8. Terminal events match employees only inside the terminal's company

- **Decision**: `RecordTerminalEventAction` (Tenancy) loads the terminal **without** HTTP context: unknown terminal → no company row is written (FR-013). Known terminal → `CompanyContext::run(terminal.companyId, …)` then match `employee_number` only among that company's employees (unique index `(company_id, employee_number)`). Same number in another company is never attached (FR-012). Unmatched number stays an event of the terminal's company with `employee_id` null; it is not attached to anyone else. There is no public terminal protocol in this feature; tests and later ingest call the Action. Duplicate employee numbers in one company throw `EmployeeNumberTakenException` (`422`, message does not mention other companies).
- **Rationale**: FR-011–FR-014. A public ingest URI is not in the spec; inventing one is out of scope.
- **Alternatives**: Matching `employee_number` globally would mix companies. HTTP ingest without a spec would invent a contract.

## R9. Super-admin action records (writes and selects only)

- **Decision**: Append-only `super_admin_action_records`: `actor_id`, `company_id`, `type` (`selected_company` | `changed_company_data`), `action` (stable code, e.g. `tenancy.selected_company`, `identity.invite_company_user`), `occurred_at`. No `updated_at`. No HTTP to list, change, or delete (FR-020, FR-021, Q2 = B). Writes: `POST` select; and an observer on `BelongsToCompany` `created`/`updated` (and equivalent state changes) when the context actor is a super admin. Views and media/export downloads do not write. No PII in the row (constitution VI.a); no passwords, emails, or file bytes.
- **Rationale**: Q2 = B. A viewer UI is out of scope (feature 001 already said so). Logs rotate and must not hold PII; the table is the review store, like `sign_in_security_events`.
- **Alternatives**: Logging every GET — Q2 rejected that. Letting company admins read the table — Q2 rejected that.

## R10. Automated isolation scan (FR-026, SC-001, SC-008)

- **Decision**: `tests/Feature/Modules/Tenancy/CompanyIsolationScanTest.php` materializes every `/api/v1/company*` route (same enumeration idea as `RoleAccessTest`) plus media GET, as an Acme company admin, against seeded Globex rows with overlapping names and employee numbers. It asserts: lists/search/counters contain only Acme; Globex ids return a 404 body identical to an unused id; Globex rows are unchanged. Named exceptions in contracts/isolation.md are skipped. SC-008: a test-only model that only uses `BelongsToCompany`, registered on a test route, is included in the same scan without extra isolation code. Background: a job that calls `CompanyContext::run` for Acme must not read Globex (FR-025).
- **Rationale**: FR-026 requires an automated walk of every function that returns or changes company data. Feature 001 already enumerates materialized routes.
- **Alternatives**: Manual checklists on each new page — fails FR-023.

## R11. No new Composer or npm packages

- **Decision**: None. Sanctum, Spatie Data, Eloquent, session, private disk, and vue-router already cover auth, DTOs, scoping, per-tab `sessionStorage`, and the SPA.
- **Rationale**: Constitution Prohibitions.
- **Alternatives**: A tenancy package would duplicate the scope and add a dependency the plan would have to justify.

## R12. Existing Actions take company id from context

- **Decision**: Company-data Actions that today call `Actor::actorCompanyId()` switch to `CompanyContext::companyId()` so a super admin with a selection can run them. They still take `Actor` when the use case needs who acted (last admin, invite, audit). Controllers stay thin. `InvalidArgumentException` for a null company id on those paths goes away; missing context is `CompanyNotSelectedException` at the HTTP middleware for super admins, or `CompanyContextRequiredException` inside Actions for jobs.
- **Rationale**: Super admins have `actorCompanyId() === null` (001 FR-015). Reusing the same Actions avoids a second code path (reuse-before-create).
- **Alternatives**: Duplicate "admin workspace" Actions — two implementations of every use case.

## R13. Frontend: composable, header, no Pinia

- **Decision**: `useSelectedCompany()` reads/writes `sessionStorage` for this tab. `resources/js/api/client.ts` attaches `X-Company-Context` when the signed-in role is `super_admin` and a selection exists. `AppHeader` always shows the selected company name for a super admin (FR-017), or a prompt to select. `/companies` is the picker (existing list). Super admin may open `/company` and `/company/users` when a company is selected (router `roles` include `super_admin`). `409 company_not_selected` sends them to `/companies`. No new npm package.
- **Rationale**: Feature 001 R12: no Pinia. `sessionStorage` is the per-tab store the two-tab edge case needs.
- **Alternatives**: Pinia global store would be shared across tabs in the same way as `localStorage`.
