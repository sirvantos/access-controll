# Research: SPA Visual Style Kit

**Feature**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) · **Limits**: [constraints.md](./constraints.md)

Each entry: Decision · Rationale · Alternatives considered. All Technical Context unknowns are resolved here.

## R1. Copy shadcn-vue New York primitives into this SPA; do not add a second UI library

- **Decision**: Install shadcn-vue **New York** (Tailwind v4 registry) by adding `components.json` (`style: "new-york"`, `cssVariables: true`, CSS file `resources/css/app.css`) and copying only the primitives existing screens need into `resources/js/components/ui/`. Runtime npm packages are those New York primitives require (`class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css`, `@lucide/vue` for the native-select chevron, `reka-ui` for Button Primitive / Label / NativeSelect `AcceptableValue`, and `@vueuse/core` for `useVModel` / `reactiveOmit`). Do **not** add PrimeVue, Vuetify, Naive UI, DaisyUI, or a wholesale admin template (FR-023). Do **not** add Radix Vue. Do **not** copy or use Reka Select or Reka Checkbox. Keep a single lucide package.
- **Rationale**: constraints.md names shadcn-vue New York on the existing Vue 3 + Tailwind v4 stack. Constitution Prohibitions require every new npm package to be named here. The current screens are unstyled zinc utilities with no shared Button/Input/Table. Existing Tailwind cannot encode New York variant maps (`cva`) or `cn()` merging; those packages exist specifically for that. Copied New York Button, Label, Input, and NativeSelect import `reka-ui` and `@vueuse/core`; omitting them makes `vue-tsc` fail. Reka Select/Checkbox would replace native `<select>` / `<input type="checkbox">` and break Vitest hooks that read `HTMLSelectElement` / `.value` / `<option>` (FR-022). Invite role, user role, time zone, and working-day controls stay native.
- **Alternatives**: (a) Restyle with ad-hoc Tailwind only — fails FR-023 (chosen component set). (b) Full Reka Select/Checkbox — changes keyboard and DOM, fails FR-022. (c) A second library beside shadcn-vue — forbidden. (d) Copy the registry files and strip `reka-ui` / `@vueuse/core` imports — `vue-tsc --noEmit` fails Definition of Done.

## R2. Semantic CSS variables map onto the named Tailwind palette; light theme only

- **Decision**: Keep `@import "tailwindcss"` in `resources/css/app.css`. Define shadcn semantic tokens on `:root` by **aliasing Tailwind v4 default palette colours** named in constraints.md (`--color-slate-50`, `--color-white`, `--color-slate-900`, `--color-slate-500`, `--color-blue-600`, `--color-red-600`, `--color-emerald-600`). Map `--primary` and `--ring` to `blue-600`, `--destructive` to `red-600`, `--background` to `slate-50`, `--card` / panel to white, `--foreground` to `slate-900`, `--muted-foreground` to `slate-500`, and a custom `--success` to `emerald-600`. Set `--radius` to `0.5rem` (8px). Do **not** add a `.dark` block, `dark:` utilities, a theme switcher, or `@custom-variant dark` that would apply dark styles by default (FR-008). Do not use shadcn’s default zinc/neutral `--primary`.
- **Rationale**: FR-001 requires one shared theme. constraints.md names Tailwind palette tokens, not free-form OKLCH. Default New York CSS uses a 10px radius (`0.625rem`) and a near-black primary — both violate constraints.md. Light-only matches the spec assumption.
- **Alternatives**: (a) Leave CLI default tokens — wrong accent and radius. (b) Dark mode “for completeness” — out of scope and excluded.

## R3. Native controls keep the same element types; New York supplies appearance

- **Decision**: Use New York **Button**, **Input**, **Label**, **NativeSelect**, **Table**, **Alert**, and **Badge**. Keep working-day and break-deducted controls as native `<input type="checkbox">` styled with the shared checkbox classes from the theme (do not copy Reka Checkbox). Keep time-zone and role fields as **native `<select>`** (NativeSelect or the same class string on `<select>`). Pass `data-testid` onto the **native** element (`<button>`, `<input>`, `<select>`, `<a>`), not a wrapper `div`. Destructive actions use Button `variant="destructive"`. Primary submits use default/primary. Secondary (select, pagination, resend, clear) use `outline` / `secondary`. Active state labels use Badge (or equivalent) with success colour; deactivated/revoked do not.
- **Rationale**: Existing specs (`SignInPage.spec.ts`, `CompanyUsersPage.spec.ts`, `CompanyProfilePage.spec.ts`) assert `.element.value`, `HTMLSelectElement`, `findAll('option')`, `setValue` on checkboxes, and `findAll('button')` testids. FR-022 forbids losing those hooks. NativeSelect is in the New York registry specifically for native `<select>`.
- **Alternatives**: Reka Select/Checkbox — rejected in R1.

## R4. Self-host IBM Plex Sans with Latin and Cyrillic; drop Bunny Instrument Sans

- **Decision**: Remove `bunny('Instrument Sans', …)` from `vite.config.ts`. Add `@fontsource/ibm-plex-sans` and import **latin and cyrillic** files for weights **400, 500, and 600** (the same weights the app already loaded for Instrument Sans). Set `--font-sans` to `"IBM Plex Sans", sans-serif` only. Do not list Inter, Roboto, Arial, or a system-ui stack as the primary family. Vite bundles the woff2 files with the CSS; the browser does not request a third-party font CDN at runtime.
- **Rationale**: Spec assumption: typeface is served by the application. Guest and signed-in copy is Russian and English (FR-019); Latin-only would fall back for Cyrillic and violate FR-007. `fonts.bunny.net` is a runtime third-party request. constraints.md names IBM Plex Sans and forbids those families as the **primary** UI typeface.
- **Alternatives**: (a) Keep Bunny CDN with IBM Plex Sans — runtime third-party, contradicts the spec assumption. (b) Latin-only @fontsource — Russian labels would not use the theme face.

## R5. App shell: slate-50 chrome, white bordered panels, compact header

- **Decision**: `App.vue` uses the theme background (`bg-background` / slate-50) and `text-foreground`. Signed-in and guest routes share that chrome. `<main>` is a white panel (`bg-card`, border, `--radius`, no heavy shadow). Guest routes still omit `AppHeader` (`route.meta.guest`). Header stays a compact top bar: border-bottom, existing visibility rules unchanged (FR-010–FR-013). Header links use accent colour and visible `focus-visible` rings. Selected company name uses primary text, `truncate` with a `title` of the raw company name so a long name stays readable (edge case). Optional: companies table row for the selected company gets a light `bg-slate-100` (or token equivalent) **in addition to** header feedback (FR-014 MAY).
- **Rationale**: FR-009, FR-006 (borders over shadows), US2 header is mandatory. Table marking is allowed and makes Select obvious without replacing the header.
- **Alternatives**: (a) Full-bleed white page — fails app-chrome token. (b) Table marking instead of header — forbidden by FR-014.

## R6. Restyle in-scope screens only; keep script, routes, and API modules unchanged

- **Decision**: Change templates (and shared primitives they import) on: `App.vue`, `AppHeader.vue`, `HomePage.vue`, `SignInPage.vue`, `ForgotPasswordPage.vue`, `ResetPasswordPage.vue`, `AcceptInvitationPage.vue`, `CompaniesPage.vue`, `CompanyUsersPage.vue`, `CompanyProfilePage.vue`. Do not change `resources/js/api/**`, router paths/meta, composable selection/auth logic, or backend PHP. Do not restyle `HealthBadge.vue` (not rendered on any in-scope screen). Do not add employee/terminal/report pages.
- **Rationale**: FR-015 list and out-of-scope rules. FR-020/FR-021. Feature tracing stays router → page → api.
- **Alternatives**: Rewriting pages into a dashboard template — wholesale replacement, forbidden.

## R7. Path alias `@` for shadcn files only; existing relative imports may stay

- **Decision**: Add Vite `resolve.alias` and `tsconfig` paths `"@/*"` → `resources/js/*` so copied New York files can import `@/lib/utils` and `@/components/ui/...`. Existing pages may keep relative imports or switch to `@/` when they import primitives. Do not rewrite the API layer as part of this feature.
- **Rationale**: shadcn-vue New York files are generated with `@/` aliases. The repo currently has no paths in `tsconfig.json`.
- **Alternatives**: Hand-edit every copied import to relative paths — brittle on CLI adds.

## R8. Focus rings come from the accent token; never `outline-none` alone

- **Decision**: Primitive focus styles use `focus-visible:ring-2 focus-visible:ring-ring` (ring = blue-600). Destructive buttons keep a visible ring and destructive fill. Do not set `outline-none` / `focus:outline-none` without that replacement (FR-017).
- **Rationale**: US4. jsdom cannot reliably assert computed rings; tests assert class contracts on primitives plus a forbidden-class scan on in-scope SFCs.
- **Alternatives**: Rely on browser default outlines only — New York often removes outline; without a ring, FR-017 fails.

## R9. Locales: no new user-facing strings unless a primitive truly needs one

- **Decision**: Keep all visible copy on existing `t()` keys. Do not hard-code labels. If a primitive requires an accessible name that is not already on screen (none identified on current pages), add the same key to `resources/js/locales/en.json` and `ru.json`. The selected-company `title` attribute is the company **name** (data), not a new locale string.
- **Rationale**: FR-019. Current screens already label fields in locale keys.
- **Alternatives**: Decorative aria-labels duplicating visible text — extra keys with no spec need.

## R10. Frontend tests prove behaviour and theme contracts; Pest and OpenAPI stay untouched

- **Decision**: Keep every existing Vitest behaviour assertion. Update a spec only if a wrapper would otherwise break a query; prefer fixing the wrapper (R3). Add tests: primitive variants (primary/destructive/success, radius not pill), forbidden treatments (no `rounded-full`, purple gradients, zinc-900 primary buttons, Instrument Sans, Inter/Roboto/Arial as `--font-sans`), selected-row marking if implemented, and an `App` + `/companies` mount that clicks Select and asserts `[data-testid="selected-company-name"]` without a router reload. Do not modify Pest tests or `public/swagger.yaml`.
- **Rationale**: SC-004, SC-005, FR-022, constitution III (frontend tests under `resources/js`). SC-002 is already store-driven; an App-level test is the missing UI proof.
- **Alternatives**: Snapshot-only restyle tests — low value and locale-fragile.

## R11. New npm packages (constitution Prohibitions)

| Package | Why existing deps cannot do the job |
|---------|-------------------------------------|
| `class-variance-authority` | New York Button/Badge variants. No CVA in the repo; repeating class strings per page would violate FR-003. |
| `clsx` | Conditional classes for `cn()`. |
| `tailwind-merge` | Tailwind v4 class conflicts inside `cn()`. |
| `tw-animate-css` | Required CSS import for shadcn-vue Tailwind v4 New York components. |
| `@lucide/vue` | New York NativeSelect chevron. No icon pack in the repo; do not use it for a logo. Keep a single lucide package. |
| `@fontsource/ibm-plex-sans` | Self-hosted IBM Plex Sans (latin + cyrillic). Current `bunny('Instrument Sans')` is the wrong face and a runtime CDN. |
| `reka-ui` | Copied New York Button, Label, Input, and NativeSelect import Button Primitive, Label, and NativeSelect `AcceptableValue` from `reka-ui`. Without the package those files cannot typecheck. Do **not** copy or use Reka Select or Reka Checkbox: invite role, user role, and time zone stay native `<select>`; working-day controls stay native `<input type="checkbox">`. |
| `@vueuse/core` | Copied New York Input and NativeSelect import `useVModel` and `reactiveOmit`. Without the package those files cannot typecheck. |

Do not add `radix-vue`, `lucide-vue-next` (unless the copied NativeSelect import path requires that exact package instead of `@lucide/vue` — then use the import the New York file ships with, not both), Pinia, Reka Select, Reka Checkbox, or any excluded UI kit. Keep a single lucide package.

## R12. Quality gate

- **Decision**: After the restyle, `make verify` must pass (SC-008). Frontend Definition of Done: `npm run test:run` and `npx vue-tsc --noEmit`. No PHP source changes, so Pest remains the existing green suite without edits.
- **Rationale**: Constitution Definition of Done + spec SC-008.
- **Alternatives**: Skip PHP in verify — the Makefile target is the named gate.
