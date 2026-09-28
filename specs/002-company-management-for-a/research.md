# Research: Company Management

**Feature**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Limits**: [constraints.md](./constraints.md)

Each entry: Decision · Rationale · Alternatives considered. All Technical Context unknowns are resolved here.

## R1. Stay in the Companies module

- **Decision**: Extend `App\Modules\Companies`. Do not add a Settings or Calendar module. Identity still talks to Companies only through `CompanyDirectory` and `CompanyDeactivated`. This feature adds `CompanySchedule` (and related readonly views) to `Companies\PublicApi` for the timesheet feature that will consume FR-011 and FR-016.
- **Rationale**: Feature 001 already owns the company record, create/list/deactivate, and the weak link to Identity. Details, time zone, and working day settings are the same business area. Constitution I.c stays intact with no recorded exception.
- **Alternatives**: A new module would force a Section I.c exception (or a second PublicApi hop) for data that belongs on the company.

## R2. Version rows with an `applies_from` UTC instant

- **Decision**: Two append-only tables owned by Companies: `company_time_zone_versions` and `company_working_day_setting_versions`. Each row has the payload and `applies_from` (UTC timestamp). The instant is the start of the next calendar day in the time zone that is in effect at the moment of the save (FR-012, FR-016). The first row of each kind, written at create, uses the start of the creation local day in the chosen time zone so that day is covered.
- **Rationale**: "The day from which it applies" is a local calendar day, but day boundaries depend on the time zone in effect at save time. A UTC instant is unambiguous on every engine and makes "which version applies at instant T?" a single comparison.
- **Alternatives**: (a) A date-only column is ambiguous around midnight when the time zone itself changes. (b) Overwriting a single settings JSON on `companies` cannot keep past days unchanged.

## R3. Two readings of "current"

- **Decision**: The profile (GET/PATCH) shows the latest version row for that company (highest `id`). Calculations for an instant T use the latest row with `applies_from <= T`. A save that changes values inserts a new row whose `applies_from` is the next local midnight; the profile therefore shows the new values immediately while today still uses the previous row.
- **Rationale**: US5-1 says the new settings become the company's current settings on save. FR-016 says today and every earlier day keep the previous settings. Those are different reads of the same history.
- **Alternatives**: Showing profile values only after they start to apply would hide a successful save until tomorrow, which contradicts US5-1.

## R4. Identical saves and several saves before the change applies

- **Decision**: If the submitted payload equals the latest version row, insert nothing. If it differs, insert a new row with the same next-midnight `applies_from` as any earlier same-day change. Lookup uses the highest `id` among rows with `applies_from <= T`, so only the last saved version applies from that moment. Rows are never deleted.
- **Rationale**: Matches the edge cases (identical save is a no-op; several same-day saves; keep every version). Append-only satisfies FR-017 and FR-012 without shrinking history.
- **Alternatives**: Updating or deleting a not-yet-applied row would drop a version the spec says to keep.

## R5. Recognised time zones

- **Decision**: The recognised set is the IANA database as exposed by PHP `DateTimeZone::listIdentifiers()`. Form Requests use Laravel's `timezone:all` rule. `GET /api/v1/time-zones` returns that same list so the create and edit dropdowns cannot drift from validation.
- **Rationale**: FR-002 and `constraints.md` name IANA identifiers. PHP 8.4 already ships that list; no dataset or package is added.
- **Alternatives**: A hardcoded SPA list would drift from the server. Enumerating every zone in `constraints.md` would duplicate IANA.

## R6. Case-insensitive search that works on SQLite and with non-ASCII names

- **Decision**: Store `companies.name_normalized` as `mb_strtolower` of the name (characters, not bytes). List search matches `name_normalized LIKE %term%` OR `bin LIKE %term%`, with the term lowercased and already trimmed. Page the filtered query (page size 15).
- **Rationale**: FR-006 requires ignoring letter case. SQLite `LOWER()` is ASCII-only; the product UI is Russian and company names will not stay Latin. A stored normalized name keeps pagination correct. BIN is digits, so case does not apply.
- **Alternatives**: `LOWER(name)` in SQL fails Cyrillic case on SQLite. Filtering in PHP after fetch breaks pages (US2-5).

## R7. Super admin sees details on create, not on a later GET

- **Decision**: `POST /admin/companies` `201` returns the saved name, time zone, optional details, default working day settings, active state, and `awaiting_first_admin`. There is no super-admin GET of details or settings. `GET /company` and `GET /company/working-day-settings` are `role:company_admin,viewer` only.
- **Rationale**: US1's independent test confirms time zone and defaults as the creating super admin. FR-020 and the edge case forbid a later details/settings request by a super admin. The 201 body is the only API that satisfies both.
- **Alternatives**: A super-admin GET of `{company}` would violate FR-020.

## R8. Own-company routes carry no company id

- **Decision**: Company admin and viewer read and (admin) change their company through `/api/v1/company` and `/api/v1/company/working-day-settings`, scoped to `Actor::actorCompanyId()`. There is no `/companies/{id}` for those roles.
- **Rationale**: US4-5 and FR-021 require a refusal that does not reveal whether another company exists. An id in the path would need a 404 that is indistinguishable from unknown, which 001 already does for users; omitting the id removes the probe.
- **Alternatives**: `/company/{company}` would work with 404, but it adds a probe surface the spec wants closed.

## R9. Viewer read vs feature 001 role tests

- **Decision**: Viewer may `GET` the two own-company routes above and is refused on `PATCH`. `tests/Feature/Modules/Identity/RoleAccessTest.php` currently asserts 403 on every materialized `/api/v1/company/*` route for a viewer; this feature updates that test so those two GET routes succeed and every other `/company/*` route (users, invitations, PATCH) stays 403. Super admin remains 403 on every `/company/*` route.
- **Rationale**: Spec 002 assumptions explicitly allow a viewer to read details and settings. Feature 001 FR-017 still covers management (no edit option).
- **Alternatives**: Leaving the 001 test unchanged would make a passing viewer GET impossible.

## R10. Past-day results without a timesheet feature

- **Decision**: This feature does not compute late arrivals or hours worked. It proves FR-016 / SC-003 by locking `CompanySchedule`: after a save, a lookup for an instant still in "today" (and any earlier instant) returns the previous version, and a lookup for an instant on or after `applies_from` returns the new version. The timesheet feature will call this PublicApi.
- **Rationale**: Spec assumptions place the calculations in the timesheet feature. Implementing them here would expand scope. The version lookup is the mechanism that keeps past results unchanged.
- **Alternatives**: Storing computed timesheet rows now would invent a feature the spec leaves out.

## R11. No new Composer or npm packages

- **Decision**: None. Validation, IANA time zones, pagination, JSON columns, and Spatie Data are already in the stack from feature 001.
- **Rationale**: Constitution Prohibitions: a new dependency must be named in this plan. Nothing here needs one.
- **Alternatives**: An extra timezone or search package would duplicate PHP and Eloquent.

## R12. Wire formats

- **Decision**: Times are `HH:MM`. Working days are the identifiers in `constraints.md` (`monday`…`sunday`), stored as a JSON list of those strings and cast through a `WeekDay` backed enum. Optional details that are submitted empty are stored as SQL `NULL` (the empty state). Company contact email is lowercased like other emails (feature 001 R14). Phone and BIN are stored as submitted after the global trim; they are not digit-stripped.
- **Rationale**: Matches `constraints.md` and "empty is not an error". Stripping phone formatting would change what the user entered; the spec does not ask for that.
- **Alternatives**: ISO weekday numbers would be magic values. Storing `''` instead of `NULL` makes BIN search and "empty" checks noisier without a spec reason.

## R13. Next-day instant and tests

- **Decision**: Compute `applies_from` with Carbon in the currently effective identifier, then convert local `Y-m-d 00:00:00` of the following day to UTC. Tests freeze the clock (`Carbon::setTestNow`) at a whole second before creating fixtures (frozen-clock rule), including a case that sits before and after Almaty midnight.
- **Rationale**: FR-012 and FR-016 are instant-boundary rules. Unfrozen clocks flake at second boundaries.
- **Alternatives**: "Tomorrow's date" without a zone is the ambiguity R2 rejects.

## R14. Backfill of companies that already exist

- **Decision**: The migration that adds the new columns and version tables inserts one time zone version (`Asia/Almaty`) and one working day settings version (FR-014 defaults) for every existing `companies` row, with `applies_from` equal to the start of that company's `created_at` day in `Asia/Almaty`. `CompanyFactory` creates the same two rows so factories used by feature 001 tests stay complete.
- **Rationale**: Feature 001 already persists companies that have only a name. After this schema they must still satisfy FR-004 (every company has settings and a time zone).
- **Alternatives**: Leaving old rows without versions would make `CompanySchedule` fail for seed data.

## R15. SPA: extend the super-admin list; add one profile page

- **Decision**: Extend `CompaniesPage.vue` and `resources/js/api/adminCompanies.ts` for search, BIN, time zone, optional details, and the time-zone dropdown. Add `CompanyProfilePage.vue` at `/company` for company admin (edit) and viewer (read-only), with `resources/js/api/companyProfile.ts`. No new npm package, no Pinia. Search and page stay in `route.query` the same way the list already keeps `page`.
- **Rationale**: Reuse the 001 pages and client. The shared `useForm` / `useManagedQueryParams` helpers named in `frontend-patterns` are not in this repository yet; copying that layer for one search box would be new structure without reuse.
- **Alternatives**: A second super-admin detail page would violate FR-020.

## R16. Concurrent profile saves

- **Decision**: No extra lock. Both requests commit; the higher `id` is current (spec edge case).
- **Rationale**: The spec says both saves complete and the last one wins. Feature 001's admin-seat lock exists for a different rule (last active admin).
- **Alternatives**: A cache lock would serialise writes the spec allows to race.

## R17. Working day length for break and grace

- **Decision**: Working day length in minutes is `end` minus `start` on the same local day. `break_duration_minutes` and `lateness_grace_minutes` are integers from 0 through length minus 1. Encoded in `app/Rules/Companies/WorkingDaySettingsRangeRule` (DataAware), not a closure in the Form Request.
- **Rationale**: `constraints.md` says "from 0 to less than the working day length". Night shifts are out of scope, so end is after start and the difference is well defined.
- **Alternatives**: A hardcoded 540-minute max would ignore a shorter working day.
