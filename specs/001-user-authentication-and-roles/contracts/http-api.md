# HTTP API Contract: User Authentication and Roles

**Feature**: [../spec.md](../spec.md) · **Data**: [../data-model.md](../data-model.md) · **Limits**: [../constraints.md](../constraints.md)

The implementation mirrors this contract into `public/swagger.yaml` (constitution Section VI).

## Conventions

- Base path: `/api/v1`. JSON only. Payloads use `snake_case`.
- Auth: Sanctum SPA cookie session (research R1). The client calls `GET /sanctum/csrf-cookie` first, then sends `X-XSRF-TOKEN` on every state-changing request. A missing or stale CSRF token returns `419`.
- Every route uses the `throttle:api` limiter (constraints.md). Exceeding it returns `429`.
- Successful bodies are wrapped `{ "data": ... }`, and paginated lists add `links` and `meta` (Laravel paginator). The only exception is `OkResource`, which returns `{ "ok": true }` unwrapped.
- Error bodies:
  - `401`: `{ "message": "Unauthenticated." }`.
  - `403`: `{ "message": "..." }`.
  - `404`: `{ "message": "..." }` (also used for another company's record, FR-018).
  - `405`: method not allowed (for example, `DELETE` on a user).
  - `409` and `410`: `{ "message": "...", "error_code": "..." }`.
  - `422`: `{ "message": "...", "errors": { field: [msg] } }`.
  - `429`: `{ "message": "...", "errors": { "email": [msg] } }` for sign-in blocks; otherwise the framework throttle body.
- Middleware aliases:
  - `current-session` (`EnsureSessionIsCurrent`, research R2) runs on every authenticated route.
  - `role:<codes>` (`EnsureUserHasRole`) returns `403` when the signed-in role is not listed.
  - `awaiting-first-admin` (`EnsureCompanyAwaitsFirstAdmin`, research R8).
- Email fields: `required|string|email|max:255`, lowercased in `toDto()`.
- Password fields: `required|string|min:8|max:128`, counted in characters, from `App\Support\Validation\PasswordRules`.

## Resources

| Resource | Shape |
|----------|-------|
| `CurrentUserResource` | `{ id, email, role, company_id }` (`company_id` is null for a super admin) |
| `CompanyUserResource` | `{ id, email, role, is_active }` |
| `PendingInvitationResource` | `{ id, email, role, expires_at }` (ISO-8601 UTC) |
| `InvitationPreviewResource` | `{ email, role, expires_at }` |
| `CompanyResource` | `{ id, name, is_active, awaiting_first_admin, created_at }` |
| `OkResource` | `{ ok: true }` (unwrapped) |

## Public endpoints (guest)

| Method & path | Body | Success | Errors | Req. |
|---------------|------|---------|--------|------|
| `POST /auth/sign-in` | `email`, `password` | `200 CurrentUserResource`; regenerates the session and stores `session_version` | `422` generic `errors.email` for a wrong password, unknown email, deactivated user, or deactivated company. `429` generic `errors.email` while an account or source block is active. `422` field validation. | FR-001, FR-003, FR-004, FR-008, FR-009, FR-011 |
| `POST /auth/forgot-password` | `email` | `200 OkResource`, always the same response for eligible, ineligible, unknown, or throttled emails | `422` only for an invalid email format | FR-010, FR-030, FR-031 |
| `POST /auth/reset-password` | `token`, `email`, `password` | `200 OkResource`; the password is changed, the token is consumed, and `session_version` is incremented | `422 errors.token` when the token is invalid, expired, or used, or the user or company is ineligible. `422` field validation. | FR-012, FR-032, FR-033 |
| `GET /invitations/{token}` | none | `200 InvitationPreviewResource` | `410 invitation_invalid` for an unknown, expired, revoked, or accepted invitation, or when the email is already registered | FR-024 |
| `POST /invitations/{token}/accept` | `password` | `200 OkResource`; the user is created as active with the invitation's company and role. It does not sign the user in. | `410 invitation_invalid`. `422` password validation. | FR-012, FR-024, FR-025 |

## Authenticated, any role (`auth:sanctum`, `current-session`)

| Method & path | Success | Errors | Req. |
|---------------|---------|--------|------|
| `GET /me` | `200 CurrentUserResource` | `401` | FR-002, FR-006 |
| `POST /auth/sign-out` | `200 OkResource`; the session is invalidated and the CSRF token is regenerated | `401` | FR-005 |

## Company administration (`role:company_admin`; scoped to the signed-in user's company)

Route ids are resolved inside Actions against `actorCompanyId()`. An id from another company returns `404` (FR-018). A super admin or viewer gets `403`.

| Method & path | Body | Success | Errors | Req. |
|---------------|------|---------|--------|------|
| `GET /company/users?page=` | none | `200` paginated `CompanyUserResource` (active and deactivated) | none | FR-016, FR-035 |
| `PATCH /company/users/{user}` | `role` ∈ {`company_admin`, `viewer`} | `200 CompanyUserResource` | `404`. `409 last_active_admin`. `422` role invalid, including `super_admin`. | FR-037, FR-038, FR-029 |
| `POST /company/users/{user}/deactivate` | none | `200 CompanyUserResource` (idempotent); `session_version` is incremented and the reset token deleted | `404`. `409 last_active_admin`. | FR-034, FR-036, FR-037 |
| `POST /company/users/{user}/reactivate` | none | `200 CompanyUserResource` (idempotent) | `404` | FR-034 |
| `DELETE /company/users/{user}` | not defined; returns `405` | none | none | FR-035, US5-6 |
| `GET /company/invitations?page=` | none | `200` paginated `PendingInvitationResource` (pending only) | none | FR-028 |
| `POST /company/invitations` | `email`, `role` ∈ {`company_admin`, `viewer`} | `201 PendingInvitationResource`; the email is queued after commit | `422 errors.email` when the email is already registered. `422` role invalid. | FR-022, FR-026, FR-029 |
| `POST /company/invitations/{invitation}/resend` | none | `200 PendingInvitationResource` with a new expiry; the old link stops working | `404`. `409 invitation_not_pending`. `422 errors.email` when the email has become registered. | FR-027 |
| `POST /company/invitations/{invitation}/revoke` | none | `200 OkResource` | `404`. `409 invitation_not_pending`. | FR-027 |

## Super admin (`role:super_admin`)

| Method & path | Body | Success | Errors | Req. |
|---------------|------|---------|--------|------|
| `GET /admin/companies?page=` | none | `200` paginated `CompanyResource` | none | FR-019 |
| `POST /admin/companies` | `name` (≤255), `first_admin_email` | `201 CompanyResource` (`awaiting_first_admin: true`). The company and the first-admin invitation are created in one transaction, and the email is queued after commit. | `422 errors.first_admin_email` when the email is already registered, in which case no company is created. `422` validation. | FR-019, FR-023, FR-026 |
| `POST /admin/companies/{company}/deactivate` | none | `200 CompanyResource` (idempotent); sessions of all company users end and their reset tokens are deleted | `404` | FR-039, US7-4 |
| `POST /admin/companies/{company}/reactivate` | none | `200 CompanyResource` (idempotent) | `404` | FR-039, US7-5 |
| `GET /admin/companies/{company}/invitations?page=` | none | `200` paginated `PendingInvitationResource` | `404` unknown company. `403` once the company has any user (`awaiting-first-admin`). | FR-020 |
| `POST /admin/companies/{company}/invitations` | `email` | `201 PendingInvitationResource`, role fixed to `company_admin` | `404`. `403` (`awaiting-first-admin`). `422 errors.email` when the email is already registered. | FR-020, FR-027 |
| `POST /admin/companies/{company}/invitations/{invitation}/resend` | none | `200 PendingInvitationResource` | `404`, including an invitation of another company. `403`. `409 invitation_not_pending`. `422 errors.email`. | FR-020, FR-027 |
| `POST /admin/companies/{company}/invitations/{invitation}/revoke` | none | `200 OkResource` | `404`. `403`. `409 invitation_not_pending`. | FR-020, FR-027 |

No route creates a super admin or assigns `super_admin` (FR-029). Every role field rejects `super_admin` with `422`.

## Emails (queued notifications)

| Notification | Recipient | Link |
|--------------|-----------|------|
| `InvitationNotification` | the invited email (on-demand route) | `{APP_URL}/invitation/{token}`; the email shows the role and expiry |
| `ResetPasswordNotification` | the user | `{APP_URL}/reset-password/{token}?email={email}` |

Both are localized (`en`, `ru`) and never contain a password.
