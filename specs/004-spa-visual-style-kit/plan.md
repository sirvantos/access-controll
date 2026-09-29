# Implementation Plan: SPA Visual Style Kit

**Branch**: `004-spa-visual-style-kit` | **Date**: 2026-09-29 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/004-spa-visual-style-kit/spec.md`. Theme values are in [constraints.md](./constraints.md).

## Summary

Restyle the existing Vue 3 SPA to shadcn-vue **New York** on Tailwind CSS v4, using cool slate chrome, white panels, blue-600 accent, red-600 danger, emerald-600 success, 8px radius, and self-hosted IBM Plex Sans — without changing auth, roles, tenancy, routes, or APIs.

- **Theme**: CSS variables alias the Tailwind palette in constraints.md (research R2). Light only.
- **Primitives**: Copied New York Button, Input, Label, NativeSelect, Table, Alert, Badge; native checkboxes (research R1, R3).
- **Shell**: `App.vue` + `AppHeader.vue` + `AppSidebar.vue` use the theme; selected company name stays in the top bar; primary nav links live in a collapsible left sidebar (overlay below `md`, in-flow from `md` up). Guest routes hide both.
- **Screens**: Guest auth/invitation pages and operator companies/users/profile/home templates only (research R6).
- **Proof**: Existing Vitest behaviour + new theme/focus/select-header tests; Pest and OpenAPI untouched (research R10).

## Technical Context

**Language/Version**: TypeScript 5.9, Vue 3.5 (`<script setup lang="ts">`). PHP 8.4 is unchanged (no application PHP in this feature).

**Primary Dependencies**: Existing: vue ^3.5, vue-router ^4.6, Tailwind CSS ^4, @tailwindcss/vite, Vite 8, Vitest 5, laravel-vite-plugin. **New npm packages** (research R11): `class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css`, `@lucide/vue`, `@fontsource/ibm-plex-sans`, `reka-ui`, `@vueuse/core`. No new Composer packages. Keep a single lucide package.

**Storage**: N/A for this feature. Selection remains tab `sessionStorage` from feature 003.

**Testing**: Vitest + Vue Test Utils + jsdom under `resources/js/**/*.spec.ts`. No new Pest files.

**Target Platform**: Evergreen-browser SPA served by Laravel (Herd locally). Desktop-first layouts; must remain usable at widths already in use.

**Project Type**: Same-origin SPA inside a single Laravel project.

**Performance Goals**: No throughput target. SC-002: selected company name (top bar) and company-data links (sidebar) appear within 1 second without a full page reload (existing composable + Vue reactivity).

**Constraints**: [constraints.md](./constraints.md). FR-020–FR-023: no API/auth/role/isolation changes; no second component library; no wholesale admin template. Fonts bundled (no runtime font CDN). Constitution: no new npm package unless named in this plan; no TODO bodies; Larastan/Pint unchanged (no PHP).

**Scale/Scope**: 4 user stories, 24 functional requirements. Screens already in the repo (guest auth + invitation, companies, company users, company profile/settings, home, app shell with responsive sidebar). No new product areas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | How this plan complies | Status |
|-----------|------------------------|--------|
| I Layer separation | No new controllers, Actions, Form Requests, or Resources. SPA pages keep calling existing `resources/js/api/**` modules. | Pass |
| I.c Weak links | No PHP module imports. | Pass |
| I.a SOLID / reuse | One shared primitive per control kind under `components/ui`; pages do not duplicate zinc button/input classes (FR-003). | Pass |
| I.a DTOs | No new HTTP bodies. | Pass |
| II Strict typing | Vue/TS strict; no magic colour strings on pages — tokens from CSS. | Pass |
| III Test-first | Behaviour tests already exist; new tests cover theme contracts and Select → header. Endpoint Pest tests are not mocked because they are not changed. | Pass |
| IV API standards | No API changes. | Pass |
| VI Observability | `public/swagger.yaml` unchanged. | Pass |
| Security | Auth strategy unchanged (Sanctum token as today). No new upload/auth surface. Fonts self-hosted (no third-party font CDN at runtime). | Pass |
| Prohibitions | New npm packages named in Dependencies / R11 with justification. No public contract change. No lowered gates. No placeholders. | Pass |

**Post-design re-check (after Phase 1)**: The same result. Data model has no tables. Contracts are UI-only. No module-boundary exceptions. No OpenAPI or backend test edits.

## Dependencies

| Package | Reason existing dependencies cannot do the job |
|---------|------------------------------------------------|
| `class-variance-authority` | New York variant maps for Button/Badge. |
| `clsx` | `cn()` conditionals. |
| `tailwind-merge` | `cn()` Tailwind conflict merging. |
| `tw-animate-css` | shadcn-vue Tailwind v4 CSS import. |
| `@lucide/vue` | New York NativeSelect chevron only (no logo). If the copied file imports `lucide-vue-next` instead, add that package and **not** both. Keep a single lucide package. |
| `@fontsource/ibm-plex-sans` | Self-hosted IBM Plex Sans latin+cyrillic; replaces Bunny `Instrument Sans`. |
| `reka-ui` | Copied New York Button, Label, Input, and NativeSelect import Button Primitive, Label, and NativeSelect `AcceptableValue` from `reka-ui`. Without the package those files cannot typecheck (`npx vue-tsc --noEmit` fails Definition of Done). Do **not** copy or use Reka Select or Reka Checkbox: invite role, user role, and time zone stay native `<select>`; working-day controls stay native `<input type="checkbox">`. |
| `@vueuse/core` | Copied New York Input and NativeSelect import `useVModel` and `reactiveOmit` from `@vueuse/core`. Without the package those files cannot typecheck. |

Do not add PrimeVue, Vuetify, Naive UI, DaisyUI, Radix Vue, Pinia, or a template kit. Do not add Reka Select or Reka Checkbox.

## Module boundary exceptions

None. This feature does not add PHP module code.

## Project Structure

### Documentation (this feature)

```text
specs/004-spa-visual-style-kit/
├── spec.md
├── constraints.md
├── plan.md              # this file
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   ├── spa-visual.md
│   ├── primitives.md
│   └── test-hooks.md
├── checklists/requirements.md
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
components.json                         # shadcn-vue New York, css: resources/css/app.css
package.json                            # + R11 packages
tsconfig.json                           # paths "@/*" → resources/js/*
vite.config.ts                          # alias @; remove bunny Instrument Sans
resources/css/app.css                   # @theme + shadcn tokens aliased to palette
resources/js/
├── app.ts                              # import fontsource latin+cyrillic 400/500/600
├── lib/utils.ts                        # cn()
├── components/
│   ├── ui/                             # copied New York primitives (R1 inventory)
│   ├── AppHeader.vue                   # top bar: menu toggle, email, company name/prompt, sign-out
│   ├── AppSidebar.vue                  # left nav; drawer below md, in-flow from md
│   └── HealthBadge.vue                 # unchanged (not in scope)
├── composables/useAppSidebar.ts        # open/close + viewport default
├── App.vue                             # slate-50 chrome, sidebar+main, guest chrome hide
├── pages/
│   ├── SignInPage.vue
│   ├── ForgotPasswordPage.vue
│   ├── ResetPasswordPage.vue
│   ├── AcceptInvitationPage.vue
│   ├── HomePage.vue
│   ├── CompaniesPage.vue               # primitives; optional selected row
│   ├── CompanyUsersPage.vue
│   └── CompanyProfilePage.vue
├── router/index.ts                     # unchanged behaviour
├── api/                                # unchanged
├── composables/                        # behaviour unchanged except useAppSidebar for shell
├── locales/{en,ru}.json                # nav.openMenu / nav.closeMenu / nav.menu (+ any a11y string)
└── **/__tests__/*.spec.ts              # existing + new theme/focus/shell integration
resources/views/welcome.blade.php       # unchanged shell (@vite already)
```

**Structure Decision**: Same single Laravel project. Visual system lives in CSS + `resources/js/components/ui`. HTTP and modules stay as in features 001–003.

## Implementation notes for tasks

- Init New York with `components.json` pointing at `resources/css/app.css` and aliases under `resources/js`. After CLI init, **overwrite** default primary/radius/dark CSS with constraints.md (R2). Do not leave zinc primary or `0.625rem` radius.
- Copy only the primitive inventory in [contracts/primitives.md](./contracts/primitives.md).
- Bind `data-testid` on native elements ([contracts/test-hooks.md](./contracts/test-hooks.md)).
- Replace per-page `class="rounded bg-zinc-900 …"` / `border-zinc-300` with primitives.
- Header/sidebar selection rules stay exactly as in `AppHeader.vue` / `AppSidebar.vue` today (`hasUsableSelection`, role checks).
- Custom left sidebar (not the shadcn-vue Sidebar registry item); toggle via top-bar button; mobile overlay + Escape/backdrop close.
- `CompanyUsersPage` `findAll('button')` testid list: do not add extra buttons when invitations are empty.
- Import `@fontsource/ibm-plex-sans` **cyrillic** and **latin** for 400/500/600.
- Do not edit `factory/`, `.cursor/`, `.specify/`, `.github/`, `Makefile`, `phpstan.neon`, `deptrac.php`, `phpunit.xml`, backend PHP, or `public/swagger.yaml`.

## Complexity Tracking

None.

## Assumptions (not stated by the spec)

- Control radius is implemented as **8px** (upper bound of the spec’s 6–8px range) via `--radius: 0.5rem`.
- Font weights **400 / 500 / 600** match the weights already loaded for Instrument Sans.
- Selected companies table row **is** marked lightly (FR-014 MAY) in addition to mandatory header feedback.
- `HealthBadge.vue` stays unstyled by this feature because it is not on an in-scope screen.
- If New York NativeSelect’s generated import is `lucide-vue-next` rather than `@lucide/vue`, that one package is used instead of `@lucide/vue` (not both).
