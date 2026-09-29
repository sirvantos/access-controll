# UI contract: themed primitives

**Feature**: [../spec.md](../spec.md) · **Research**: [../research.md](../research.md)

Source: shadcn-vue **New York** (Tailwind v4), copied into `resources/js/components/ui/`. Helper: `resources/js/lib/utils.ts` (`cn`).

## Inventory (only these for this feature)

| Name | Registry / implementation | Used on |
|------|---------------------------|---------|
| Button | `ui/button` | All forms, table actions, pagination, sign-out |
| Input | `ui/input` | All text/email/password/search/number/time fields |
| Label | `ui/label` | Field labels |
| NativeSelect | `ui/native-select` | Time zone, invite role, user role |
| Table | `ui/table` | Companies, invitations, users |
| Alert | `ui/alert` | Non-field page messages where a banner is appropriate |
| Badge | `ui/badge` | Active / deactivated (success only for Active) |
| Checkbox classes | shared class string on native checkbox | Working days, break deducted |

Do not add Dialog, Sidebar, Sonner, Chart, or other registry items in this feature.

## Button variants

| Variant | Maps to | Examples |
|---------|---------|----------|
| default / primary | accent fill, white label | Sign in, create company, invite, save |
| secondary or outline | bordered, not accent fill | Select, pagination, resend, clear selection, reactivate |
| destructive | danger fill or danger border+text | Deactivate, revoke |

Radius from `--radius`. No `rounded-full`.

## Focus

Every interactive primitive includes a visible `focus-visible` ring using `--ring` (blue-600). Destructive controls keep the ring.

## `data-testid` placement

Place the existing test id on the **native control**, not a wrapper:

- `[data-testid="email"]` → `<input>`
- `[data-testid="invite-role"]`, `[data-testid="user-role-*"]`, `[data-testid="company-time-zone"]` → `<select>` that still has `<option>` children
- `[data-testid="break-deducted"]`, `[data-testid="working-day-*"]` → `<input type="checkbox">`
- `[data-testid="sign-in"]`, `[data-testid="sign-out"]`, table action ids → `<button>`
- `[data-testid="forgot-password"]`, header nav → `RouterLink` (`<a>`)

`CompanyUsersPage` enumerates `findAll('button')` testids when invitations are empty. Do not add extra unlabeled buttons on that page.

## Selected company row (optional)

If implemented: the `tr[data-testid="company-row"]` whose `company.id` equals the tab selection gets a light selected background. Header feedback remains required.
