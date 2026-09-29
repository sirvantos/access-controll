# Data Model: SPA Visual Style Kit

**Feature**: [spec.md](./spec.md) · **Limits**: [constraints.md](./constraints.md) · **Decisions**: [research.md](./research.md)

This feature adds **no** database tables, Eloquent models, or API resources. Theme values are CSS variables and Tailwind `@theme` entries. Runtime selection/auth state stays as in features 001–003.

## Theme

Shared visual values. Single source: [constraints.md](./constraints.md). Implemented as CSS custom properties on `:root` (research R2).

| Field | Source | Rules |
|-------|--------|--------|
| App chrome background | `slate-50` | Page behind header and panels |
| Panel surface | white | Cards, forms, tables |
| Primary text | `slate-900` | Headings, body, table cells, selected company name |
| Muted text | `slate-500` | Hints, secondary header copy |
| Accent | `blue-600` | Primary buttons, links, focus ring |
| Danger | `red-600` | Destructive buttons, inline errors, alerts |
| Success | `emerald-600` | Positive state labels only (e.g. Active) |
| Radius | 8 px (`0.5rem`) | Controls; not pills (`rounded-full` forbidden) |
| Elevation | borders | No glow, no oversized soft shadow |
| Typeface | IBM Plex Sans | Primary; fallback `sans-serif`; latin + cyrillic files |

There is no theme entity in storage and no dark-mode state.

## Themed primitive

A reusable Vue control whose appearance comes from the Theme. Copied New York sources live under `resources/js/components/ui/`. Pages must not restyle the same control with one-off zinc/black utilities.

| Primitive | Element contract (FR-022) | Variants / notes |
|-----------|---------------------------|------------------|
| Button | Native `<button>` | `default` (accent), `secondary`/`outline`, `destructive` (danger). No pill radius. |
| Input | Native `<input>` | Text, email, password, search, number, time. `data-testid` on the input. |
| Label | Native `<label>` | Existing locale text. |
| NativeSelect | Native `<select>` + `<option>` | Time zones, invite role, user role. Not Reka Select. |
| Checkbox | Native `<input type="checkbox">` | Working days, break deducted. Not Reka Checkbox. |
| Table | `<table>` / thead / tbody / tr / td | Existing `data-testid` on rows. |
| Field error | `<p>` (or Alert description) | Danger colour; existing error testids. |
| Alert | Theme Alert | Page-level messages (sign-in failure, invitation invalid) when not a field error. |
| State label | Badge or equivalent | Success colour iff Active; deactivated/revoked not success. |
| Header nav item | `RouterLink` / button | Accent links; sign-out remains a button with `data-testid="sign-out"`. |

### Validation

- Same primitive looks the same on every in-scope screen (FR-003).
- Pill-shaped controls forbidden (FR-005).
- Focus: accent ring visible; no outline removal without replacement (FR-017).

## Selected company indicator

Already specified in feature 003. This feature only changes **presentation**.

| Field | Behaviour (unchanged) | Presentation |
|-------|----------------------|--------------|
| Name | `useSelectedCompany().selectedCompany.name` when `hasUsableSelection` | Header `[data-testid="selected-company-name"]`; primary text; truncate + `title` = name |
| Prompt | Super admin without usable selection | `[data-testid="select-company-prompt"]` |
| Company-data links | Super admin only when usable selection | Unchanged `v-if` |
| Table row mark | Optional (FR-014) | Light selected background on matching `company-row`; does not replace header |

No new client store. No new session keys.

## State transitions (visual only)

```text
Control: default → hover → focus-visible (accent ring) → disabled
Button: default | secondary | destructive
Company state label: Active (success) | Deactivated (not success)
Super admin header: prompt ⇄ selected name (existing composable)
```

No backend state changes.
