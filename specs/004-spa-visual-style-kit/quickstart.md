# Quickstart & Validation: SPA Visual Style Kit

**Feature**: [spec.md](./spec.md) · **Contracts**: [contracts/spa-visual.md](./contracts/spa-visual.md), [contracts/primitives.md](./contracts/primitives.md), [contracts/test-hooks.md](./contracts/test-hooks.md) · **Limits**: [constraints.md](./constraints.md)

This is a validation guide. Implementation detail belongs in `tasks.md`.

## Prerequisites

- Features 001–003 SPA screens exist (sign-in, invitations, companies, company users, company profile, selected-company header).
- PHP 8.4, Composer, Node `^20.19 || >=22.12`.
- `composer install`, `npm install`, `.env` from `.env.example`.
- No new backend migrations.

## Automated gates (Definition of Done)

```bash
make verify
```

While iterating (frontend-only):

```bash
npm run test:run
npx vue-tsc --noEmit
```

Do **not** change Pest files or OpenAPI. Existing `php artisan test --compact` coverage stays green without modification.

Test conventions:

- Behaviour assertions stay on `data-testid` hooks in [contracts/test-hooks.md](./contracts/test-hooks.md).
- Compare copy with `t(...)`, not pasted Russian/English sentences.
- Mock `resources/js/api/**` in page tests as today.

## Scenario map

Each acceptance scenario in `spec.md` needs an automated test.

| Story | Test file | Key checks |
|-------|-----------|------------|
| US1 company admin consistency | `CompanyUsersPage.spec.ts`, `CompanyProfilePage.spec.ts` (behaviour unchanged); new primitive specs | Invite 422 still inline; working-day 422 still inline; Active uses success treatment; deactivate/revoke use destructive; viewer read-only profile unchanged. Primitive tests: same Button/Input/Table/NativeSelect classes. |
| US2 super admin select | `CompaniesPage.spec.ts` (select/clear store); `AppHeader.spec.ts` (name vs prompt, links); **new** App-level spec | Click Select → `[data-testid="selected-company-name"]` and company-data links appear without remounting the document. Clear / rejected selection restores prompt. Optional selected row does not remove header feedback. |
| US3 guests | `SignInPage.spec.ts`, `ForgotPasswordPage.spec.ts`, `ResetPasswordPage.spec.ts`, `AcceptInvitationPage.spec.ts` | Failure/confirmation still on existing testids; header not rendered on guest routes (`App.vue` + router `meta.guest`). Primitive/theme scan covers typeface and accent on these pages. |
| US4 focus | new primitive spec | Primary, destructive, outline buttons, Input, NativeSelect, checkbox, header link classes include `focus-visible` + ring token. Forbidden scan: no bare `outline-none` without ring. |
| SC-004 / FR-021 | (none new) | No PHP/OpenAPI diff. |
| SC-007 | new scan spec | In-scope Vue/CSS: no `rounded-full` on controls, no purple gradient utilities, no Instrument Sans / Inter / Roboto / Arial as `--font-sans` primary, no `bg-zinc-900` primary buttons. |
| SC-008 | `make verify` | Full repository gate. |

## Manual smoke run

1. Sign in as a company admin. Open `/company/users` and `/company`. Confirm shared typeface, accent primary buttons, bordered fields, tables, and danger inline errors (submit invalid invite / invalid settings).
2. Confirm Active labels are emerald; deactivate/revoke are visually danger.
3. Sign in as a viewer. `/company` is themed and read-only (no save controls).
4. Sign in as a super admin with no selection. `/companies` is themed; header shows the select prompt; no company-data links. Press Select: header shows the company name and links **without** a full reload. Optional: selected table row is lightly marked. Clear selection: prompt returns, links go away.
5. Signed out: `/sign-in`, forgot/reset password, invitation accept use the same typeface and accent; no signed-in header.
6. Tab through header, fields, and actions: focus ring is visible in the accent colour, including destructive actions.
7. Switch UI language ru/en: labels still come from locales; header and tables remain usable.
8. Narrow the window to a width already used today: controls stay reachable.
9. In DevTools, disable the IBM Plex Sans network/font: text still readable via `sans-serif`.
