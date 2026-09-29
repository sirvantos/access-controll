# Constraints: SPA Visual Style Kit

Single source of truth for theme values referenced by `spec.md`, `plan.md`, tasks, and frontend tests of this feature. Update this file together with every dependent artifact when a value changes.

## Component set

| Item | Value |
|------|-------|
| Component set | shadcn-vue |
| Style | New York |
| Base stack | Existing Vue 3 + Tailwind CSS v4 |
| Additional UI component libraries | None (PrimeVue, Vuetify, Naive UI, DaisyUI themes, and similar are excluded) |
| Admin templates | None used as a wholesale replacement of the SPA |

## Colour tokens

Colour names refer to the Tailwind CSS default palette.

| Token | Value | Use |
|-------|-------|-----|
| App chrome background | `slate-50` | Page background behind panels and header area |
| Panel surface | white | Content panels, cards, tables, form areas |
| Primary text | `slate-900` | Body text, headings, table content |
| Muted text | `slate-500` | Secondary text, hints, table meta |
| Accent | `blue-600` | Primary actions, links, focus indicators |
| Danger | `red-600` | Deactivate, revoke, validation errors |
| Success | `emerald-600` | Positive state labels such as "Active" |

## Shape and elevation

| Item | Value |
|------|-------|
| Control radius | 6–8 px |
| Control radius (implemented) | 8 px (`0.5rem` `--radius`) |
| Pill-shaped controls | Not allowed |
| Elevation | Minimal; borders preferred over shadows |

## Typography

| Item | Value |
|------|-------|
| UI typeface | IBM Plex Sans |
| Weights loaded | 400, 500, 600 |
| Scripts loaded | Latin and Cyrillic |
| Fallback | Generic `sans-serif` only (not a system-ui stack as primary) |
| Excluded as primary UI typeface | Inter, Roboto, Arial, browser system font stack |

## Excluded visual treatments

- Purple-on-white gradients
- Warm cream paper themes with serif display type
- Terracotta accents
- Dark mode by default (no dark mode toggle in this feature)
- Glow effects
- Oversized soft shadows
