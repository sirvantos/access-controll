# UI contract: preserved test hooks

**Feature**: [../spec.md](../spec.md) · **FR-022**

Existing Vitest files stay the source of behaviour. Markup/classes may change; these selectors and element types must keep working.

## Header / shell (`AppHeader.spec.ts`, `App.spec.ts`, `selectCompanyHeader.spec.ts`)

`current-email`, `sign-out`, `sidebar-toggle`, `app-sidebar`, `app-sidebar-nav`, `sidebar-backdrop` (mobile when open), `companies-link`, `select-company-prompt`, `selected-company-name`, `company-users-link`, `company-profile-link`.

`companies-link` / `company-users-link` / `company-profile-link` remain anchors with `href` as today; they live in the sidebar. Selected company name / prompt stay in the top bar.

## Auth / invitation pages

`email`, `password`, `sign-in`, `sign-in-error`, `forgot-password`, `send-reset-link`, `reset-confirmation`, `email-error`, `reset-password`, `password-error`, `reset-token-error`, `request-new-link`, `invitation-email`, `invitation-role`, `invitation-invalid`, `accept-invitation`.

Sign-in email/password remain `HTMLInputElement` (`.value` / `setValue`).

## Companies

`company-name`, `company-time-zone`, field `*-error` ids, `first-admin-email`, `create-company`, `company-search`, `companies-empty`, `company-row`, `company-bin-{id}`, `select-company-{id}`, `deactivate-company-{id}`, `reactivate-company-{id}`, `clear-selected-company`, `first-admin-invitations-{id}`, `invitation-error-{id}`, `replacement-form-{id}`, `replacement-email-{id}`, `invite-replacement-{id}`, `invitation-row`, `resend-invitation-{id}`, `revoke-invitation-{id}`, `previous-page`, `next-page`.

No `delete-company*` control.

## Company users

`invite-email`, `invite-role` (`<select>` + `option[value=…]`), `invite-error`, `invite-user`, `invitation-error`, `invitation-row`, `user-row`, `user-role-{id}` as `HTMLSelectElement`, `deactivate-user-{id}`, `reactivate-user-{id}`, `user-error`, pagination ids. No `delete-user`. No `super_admin` option.

## Company profile

`company-profile-form`, `company-profile-readonly`, `working-day-settings-form`, field testids and `*-error` ids, `save-company-profile`, `save-working-day-settings`. Viewer: values as text nodes except `break-deducted` which stays a **disabled checkbox**.

## Home

`home-placeholder`.

## New tests (this feature)

May add primitive class contracts, a forbidden-utility scan, selected-row assertion, and an App-level Select → header name assertion. They must not replace the behaviour checks above.
