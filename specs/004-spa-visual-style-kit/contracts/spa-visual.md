# UI contract: SPA visual system

**Feature**: [../spec.md](../spec.md) · **Limits**: [../constraints.md](../constraints.md) · **Routes**: [../../001-user-authentication-and-roles/contracts/spa-routes.md](../../001-user-authentication-and-roles/contracts/spa-routes.md), [../../003-data-isolation-between-companies/contracts/spa-routes.md](../../003-data-isolation-between-companies/contracts/spa-routes.md)

This feature does **not** change HTTP URIs, methods, bodies, or status codes. `public/swagger.yaml` is unchanged.

## Routes and shell (behaviour unchanged)

| Path | Page | Header |
|------|------|--------|
| `/sign-in`, `/forgot-password`, `/reset-password/:token`, `/invitation/:token` | Guest pages | Hidden (`meta.guest`) |
| `/` | `HomePage.vue` | Shown when signed in |
| `/companies` | `CompaniesPage.vue` | Super admin |
| `/company/users` | `CompanyUsersPage.vue` | Company admin; super admin with selection |
| `/company` | `CompanyProfilePage.vue` | Company admin, viewer; super admin with selection |

After Select on `/companies`: `useSelectedCompany().setSelectedCompany` runs as today; the header must show the name and company-data links **without** a full document reload.

## Theme tokens (must match constraints.md)

Screens consume semantic classes (`bg-background`, `bg-card`, `text-foreground`, `text-muted-foreground`, `bg-primary`, `text-destructive`, `text-success` or Badge success, `ring-ring`, `rounded-md`/`rounded-lg` from `--radius`), not per-page `bg-zinc-900` / `border-zinc-300`.

| Token | Tailwind source | Role |
|-------|-----------------|------|
| background | slate-50 | App chrome |
| card | white | Panels |
| foreground | slate-900 | Primary text |
| muted-foreground | slate-500 | Muted text |
| primary / ring | blue-600 | Actions, links, focus |
| destructive | red-600 | Deactivate, revoke, errors |
| success | emerald-600 | Active labels |
| radius | 8px | Controls |

## Forbidden on in-scope screens

- Purple-on-white gradients, cream/serif marketing look, terracotta, glow, oversized soft shadows
- `rounded-full` on controls
- Dark mode as default (`class="dark"` on `html`/`body`, `.dark { … }` applied by default)
- Inter, Roboto, Arial, or system stack as `--font-sans` primary
- Instrument Sans remaining as `--font-sans`
- PrimeVue, Vuetify, Naive UI, DaisyUI, or a replacement admin layout

## Copy

All visible strings stay on existing locale keys via `t()`. Both `en` and `ru` must keep working. No hard-coded UI sentences in components.
