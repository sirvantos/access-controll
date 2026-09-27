# SPA Route Contract: User Authentication and Roles

**Feature**: [../spec.md](../spec.md) · **API**: [http-api.md](./http-api.md)

The router lives in `resources/js/router/index.ts` (vue-router, research R12). `routes/web.php` serves the SPA shell for every non-API path: every path except `api/*`, `sanctum/*`, `up`, and `health`. HTTP calls are made only from `resources/js/api/**`. All copy uses `t()` keys that exist in both `resources/js/locales/en.json` and `ru.json`.

| Path | Page | Access | Behaviour |
|------|------|--------|-----------|
| `/sign-in` | `SignInPage.vue` | guest only | Email and password form. Shows the generic failure message (422) and the block message (429). On success, loads `/me` and redirects to the role's home page. |
| `/forgot-password` | `ForgotPasswordPage.vue` | guest only | Email form. Always shows the same confirmation. |
| `/reset-password/:token` | `ResetPasswordPage.vue` | guest only | Reads `email` from the query string. Takes a new password. A 422 on `token` shows "request a new link" with a link to `/forgot-password`. |
| `/invitation/:token` | `AcceptInvitationPage.vue` | guest only | Loads the preview. A 410 shows "invitation no longer valid". Otherwise it shows the email and role and asks for a password. On success, redirects to `/sign-in?email=`. |
| `/` | `HomePage.vue` | any signed-in role | Redirects a super admin to `/companies` and a company admin to `/company/users`. A viewer stays on a placeholder home page, because events, timesheets, and reports belong to later features. |
| `/company/users` | `CompanyUsersPage.vue` | `company_admin` | Lists users and pending invitations. Offers invite, re-send, revoke, deactivate, reactivate, and a role change between `company_admin` and `viewer`. There is no delete control and no `super_admin` option. A 409 `last_active_admin` shows its localized explanation. |
| `/companies` | `CompaniesPage.vue` | `super_admin` | Lists companies. Offers create (name and first-admin email), deactivate, and reactivate. While `awaiting_first_admin`, it shows first-admin invitations with re-send, revoke, and invite-replacement. |

Guards:
- A signed-out visitor who opens a protected route is sent to `/sign-in`.
- A signed-in user who opens a guest-only route is sent to `/`.
- A role mismatch sends the user to `/`.
- Any 401 from the API clears the current user and sends them to `/sign-in`.

A header shows the signed-in email and a sign-out button on every protected page.
