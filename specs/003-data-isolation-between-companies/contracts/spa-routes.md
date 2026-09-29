# SPA Route Contract: Data Isolation Between Companies

**Feature**: [../spec.md](../spec.md) · **API**: [http-api.md](./http-api.md)

Guests, sign-in, invitations, and `/companies` list/create/deactivate stay as in [feature 001](../../001-user-authentication-and-roles/contracts/spa-routes.md) and [feature 002](../../002-company-management-for-a/contracts/spa-routes.md). HTTP calls stay in `resources/js/api/**`. Copy uses `t()` keys in both `resources/js/locales/en.json` and `ru.json`.

| Path | Page | Access | Behaviour |
|------|------|--------|-----------|
| `/companies` | `CompaniesPage.vue` (extended) | `super_admin` | Existing list plus a **select** control per company (including deactivated). Select calls `POST /api/v1/admin/selected-company`, stores `{ id, name }` in **`sessionStorage`** for this tab, and shows that name in the header. A **clear** control removes tab storage and calls `DELETE /api/v1/admin/selected-company`. |
| `/company` | `CompanyProfilePage.vue` | `company_admin`, `viewer`, and `super_admin` **with a selected company** | Super admin uses the same page as a company admin of the selected company (edit allowed). Without a selection, the guard sends the super admin to `/companies` with the select prompt. |
| `/company/users` | `CompanyUsersPage.vue` | `company_admin`, and `super_admin` **with a selected company** | Super admin may invite, resend, revoke, deactivate, reactivate, and change roles as a company admin of the selected company (FR-022). Last-active-admin `409` still applies. |

Header (every protected page):

- Super admin: the **selected company name** is always visible when one is selected (FR-017); otherwise the prompt to select (key in both locales). Links: `/companies`; `/company` and `/company/users` only useful after select.
- Company admin / viewer: unchanged (no company picker; they have one company).

Client:

- `useSelectedCompany()` is tab-local (`sessionStorage`), not Pinia, so two tabs can hold different companies.
- `api/client.ts` sends `X-Company-Context` when `currentUser.role === 'super_admin'` and this tab has a selection.
- `409` `company_not_selected` → clear is not required; navigate to `/companies` and show the prompt.
- `404` on a company-data id is the same empty/not-found UI as an unknown id (no extra "wrong company" copy).

Router tests: super admin without selection is sent away from `/company` and `/company/users`; with selection both stay. Company admin still cannot open `/companies`. Two-tab behaviour is proven by two `sessionStorage` states in the composable test (not a real second browser).
