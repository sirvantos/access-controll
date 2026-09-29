# SPA visual style kit

Restyle the Vue 3 SPA for this multi-company access-control CRM so it looks like a calm, modern B2B product UI, without changing authentication, roles, tenancy, or API behaviour.

## Goal

Replace the current plain white/zinc utility look with a coherent visual system that operators can trust for daily work: companies, users, invitations, company profile, working-day settings, and sign-in flows. The service stores sensitive personal and biometric data; the UI must feel precise and restrained, not like a marketing landing page.

## Chosen visual system

Use **shadcn-vue** components in the **New York** style, built on the existing **Vue 3 + Tailwind CSS v4** stack.

Theme tokens:

- Surfaces: cool slate background (`slate-50` app chrome, white panels)
- Text: `slate-900` primary, `slate-500` muted
- Accent: `blue-600` for primary actions, focus rings, and links
- Danger: `red-600` for deactivate, revoke, and validation errors
- Success / active: `emerald-600` for positive state labels such as Active
- Radius: small (about 6–8px), no pill-shaped controls
- Elevation: minimal; prefer borders over heavy shadows
- Typography: **IBM Plex Sans** for UI text (not Inter, Roboto, Arial, or the browser system stack)

Do **not** use purple-on-white gradients, warm cream paper themes with serif display type, terracotta accents, dark-mode-by-default, glow effects, or oversized soft shadows.

## What must change

1. Introduce a shared theme (CSS variables / Tailwind theme) and base primitives needed by existing screens: buttons, text inputs, selects, tables, inline errors/alerts, and compact header chrome.
2. Restyle the app shell (`App` layout and header) so spacing, type, and navigation match the theme.
3. Restyle the main operator screens that already exist: sign-in / password flows, companies list and create form, company users and invitations, company profile, working-day settings, and guest invitation acceptance where present.
4. Keep Russian and English UI copy via existing locale keys; do not hard-code new user-facing strings in components.
5. Preserve current routes, roles, and behaviour. After a super admin selects a company, the selected company name must remain clearly visible in the header, and company-data navigation must still appear only when a usable selection exists.
6. Selecting a company must remain an obvious success for the operator: the header reflects the selected company immediately; the companies table may also mark the selected row lightly, but header feedback is mandatory.

## What must not change

- Backend APIs, Form Requests, Actions, Resources, migrations, and OpenAPI contracts
- Auth strategy (Sanctum session / current token approach)
- Role rules and company isolation rules
- Existing test semantics for API behaviour; update frontend tests only where markup/classes or visible structure change
- Do not replace the SPA with a third-party admin template wholesale
- Do not add an unrelated component library (PrimeVue, Vuetify, Naive UI, DaisyUI themes, etc.) alongside shadcn-vue

## Acceptance ideas for the author

- A signed-in company admin sees consistent buttons, form fields, and tables across company users and profile pages.
- A super admin on `/companies` sees the new theme; after Select, the header shows the selected company name and company-data links without a full page reload.
- Guest sign-in and invitation pages use the same typography and accent colour.
- Keyboard focus states are visible on primary controls.
- `make verify` stays green after the restyle.

## Out of scope

- New business features (employees, terminals, reports UI beyond what already exists)
- Dark mode toggle
- Mobile-native redesign (layouts may remain desktop-first, but must not break narrow widths already in use)
- Rebranding with a custom logo asset beyond typography and colour tokens
