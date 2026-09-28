# SPA Route Contract: Company Management

**Feature**: [../spec.md](../spec.md) · **API**: [http-api.md](./http-api.md)

Guards, guest routes, `/company/users`, and the header sign-out control stay as in [feature 001 spa-routes.md](../../001-user-authentication-and-roles/contracts/spa-routes.md). HTTP calls stay in `resources/js/api/**`. Copy uses `t()` keys in both `resources/js/locales/en.json` and `ru.json`.

| Path | Page | Access | Behaviour |
|------|------|--------|-----------|
| `/companies` | `CompaniesPage.vue` (extended) | `super_admin` | Lists every company (active and deactivated) with name, BIN (blank when empty), state, and creation date, paginated. A search field (query `search`, with `page`) narrows by part of name or BIN, ignoring case; no matches shows the empty-list copy. Create takes name, first-admin email, time zone (pre-filled `Asia/Almaty`, options from `GET /time-zones`), and optional BIN, contact person, phone, email. Field errors from 422 are shown per field. Deactivate and reactivate stay as in feature 001. There is no delete control. There is no opening of working day settings. |
| `/company` | `CompanyProfilePage.vue` (new) | `company_admin`, `viewer` | Loads `GET /company` and `GET /company/working-day-settings`. A company admin can save details (including time zone from `GET /time-zones`) and can save working day settings as two separate forms. A viewer sees the same values with no edit controls; a viewer PATCH is not offered and would be 403. |
| `/` | `HomePage.vue` | any signed-in role | Unchanged redirects for super admin and company admin. A viewer still lands on the placeholder home; the header link below is how they open `/company`. |

Header (on every protected page) adds role-filtered links, still using `t()`:

- Super admin: `/companies`
- Company admin: `/company/users`, `/company`
- Viewer: `/company`

Router tests: a viewer on `/company` stays; a viewer on `/company/users` still goes to `/`; a super admin on `/company` goes to `/`; a company admin of the session sees only their company data on `/company`.
