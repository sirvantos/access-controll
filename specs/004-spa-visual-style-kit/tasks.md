---
description: "Task list for SPA Visual Style Kit"
---

# Tasks: SPA Visual Style Kit

**Input**: Design documents from `specs/004-spa-visual-style-kit/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [constraints.md](./constraints.md), [contracts/spa-visual.md](./contracts/spa-visual.md), [contracts/primitives.md](./contracts/primitives.md), [contracts/test-hooks.md](./contracts/test-hooks.md), [quickstart.md](./quickstart.md)

**Tests**: Required. Constitution §III (test-first), [research.md](./research.md) R10, and [quickstart.md](./quickstart.md) §Scenario map ask for Vitest coverage of every acceptance scenario. Write the failing frontend test first, then the Vue/CSS that makes it pass. A task never leaves a failing test behind. Keep every existing behaviour assertion in `resources/js/**/*.spec.ts`; update a spec only when markup, classes, or visible structure changed. Do not add or edit Pest tests or `public/swagger.yaml`.

**Organization**: Tasks are grouped by user story. Phases follow the spec: Setup → Foundational (theme + primitives + shell) → P1 US1 → P1 US2 → P2 US3 → P2 US4 → Polish.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with the other [P] tasks of the same phase (different files, no dependency between them).
- **[Story]**: The user story the task serves (US1…US4). Setup and Foundational have no story label. Polish has no story label.
- Include exact file paths in descriptions.

## Rules for every task

- Theme values come only from [constraints.md](./constraints.md). Do not invent colours, radii, or typefaces. Implemented control radius is **8 px** (`0.5rem` `--radius`). Colour tokens (Tailwind default palette): App chrome background `slate-50`; Panel surface white; Primary text `slate-900`; Muted text `slate-500`; Accent `blue-600`; Danger `red-600`; Success `emerald-600`. UI typeface **IBM Plex Sans**; weights **400, 500, 600**; scripts **Latin and Cyrillic**; fallback generic `sans-serif` only. Pill-shaped controls are not allowed. Elevation is minimal; borders preferred over shadows.
- Excluded as primary UI typeface: Inter, Roboto, Arial, browser system font stack. Excluded visual treatments: purple-on-white gradients; warm cream paper themes with serif display type; terracotta accents; dark mode by default (no dark mode toggle); glow effects; oversized soft shadows.
- Component set is **shadcn-vue**, style **New York**, on the existing Vue 3 + Tailwind CSS v4 stack. Copy only the inventory in [contracts/primitives.md](./contracts/primitives.md). Do not add Dialog, Sidebar, Sonner, Chart, or other registry items. Do not add PrimeVue, Vuetify, Naive UI, DaisyUI, Radix Vue, Pinia, or a wholesale admin template. Do **not** copy or use Reka Select or Reka Checkbox: invite role, user role, and time zone stay native `<select>`; working-day controls stay native `<input type="checkbox">`. If New York NativeSelect imports `lucide-vue-next` instead of `@lucide/vue`, add that one package and **not** both (plan §Assumptions). Keep a single lucide package.
- New npm packages allowed only those named in [plan.md](./plan.md) Dependencies / research R11: `class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css`, `@lucide/vue` (or the single lucide package NativeSelect actually imports), `@fontsource/ibm-plex-sans`, `reka-ui`, `@vueuse/core`. Copied New York Button, Label, Input, and NativeSelect cannot typecheck without `reka-ui` (Button Primitive, Label, NativeSelect `AcceptableValue`) and `@vueuse/core` (`useVModel`, `reactiveOmit`). No new Composer packages.
- Keep native element types: Button → `<button>`; Input → `<input>`; NativeSelect → `<select>` with `<option>` children; checkboxes stay `<input type="checkbox">` (do not copy Reka Checkbox). Place existing `data-testid` values on the **native** control, not a wrapper `div` ([contracts/test-hooks.md](./contracts/test-hooks.md)).
- `CompanyUsersPage` enumerates `findAll('button')` testids when invitations are empty. Do not add extra unlabeled buttons on that page.
- Focus: every interactive primitive uses a visible `focus-visible` ring with `--ring` (`blue-600`). Do not set `outline-none` / `focus:outline-none` without that replacement (FR-017, research R8). Destructive controls keep the ring.
- All user-facing copy stays on existing `t()` keys in `resources/js/locales/en.json` and `resources/js/locales/ru.json`. Do not hard-code UI sentences. The selected-company `title` attribute is the company **name** (data), not a new locale string. If a primitive truly needs a new accessible name, add the same key to both locale files in that task.
- Do not change `resources/js/api/**`, router paths/meta, composable selection/auth logic, backend PHP, Form Requests, Actions, Resources, migrations, OpenAPI, or `HealthBadge.vue`. Do not edit `factory/`, `.cursor/`, `.specify/`, `.github/`, `Makefile`, `phpstan.neon`, `deptrac.php`, or `phpunit.xml`.
- Vue SFCs use `<script setup lang="ts">`. Tests compare copy with `t(...)`, never pasted Russian/English sentences. Mock `resources/js/api/**` in page tests as today.
- Follow `.cursor/skills/frontend-patterns/SKILL.md`, `.cursor/skills/vue-api-integration/SKILL.md`, `.cursor/skills/tailwindcss-development/SKILL.md`, and `.cursor/skills/pest-testing/SKILL.md` only if a Vitest file needs a Pest-style assertion pattern; this feature is frontend-only (Vitest).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: shadcn-vue New York wiring, path alias, `cn()`, and self-hosted IBM Plex Sans. The Vue 3 + Tailwind v4 SPA already exists.

- [x] T001 Add the npm packages named in [plan.md](./plan.md) Dependencies to `package.json` and install them: `class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css`, `@lucide/vue`, `@fontsource/ibm-plex-sans`, `reka-ui`, `@vueuse/core`. Copied New York Button, Label, Input, and NativeSelect cannot typecheck without `reka-ui` and `@vueuse/core`. Do not add PrimeVue, Vuetify, Naive UI, DaisyUI, Radix Vue, Pinia, Reka Select, Reka Checkbox, or a second lucide package unless NativeSelect’s copied import requires `lucide-vue-next` instead of `@lucide/vue` (then that one, not both). Keep a single lucide package. No Composer changes.
- [x] T002 [P] Create `components.json` for shadcn-vue New York (`style: "new-york"`, `cssVariables: true`) with CSS file `resources/css/app.css` and aliases under `resources/js` (`@/components`, `@/lib/utils`, `@/components/ui`) per [plan.md](./plan.md) Project Structure.
- [x] T003 [P] Add Vite `resolve.alias` and TypeScript paths `"@/*"` → `resources/js/*` in `vite.config.ts` and `tsconfig.json` so copied New York files can import `@/lib/utils` and `@/components/ui/...` (research R7). Do not rewrite `resources/js/api/**` imports.
- [x] T004 Create `resources/js/lib/utils.ts` exporting `cn()` from `clsx` + `tailwind-merge` (research R1). Test first in `resources/js/lib/__tests__/utils.spec.ts`: `cn()` merges classes and later Tailwind utilities win on conflict.
- [x] T005 Remove `bunny('Instrument Sans', …)` from `vite.config.ts`. Import `@fontsource/ibm-plex-sans` **latin** and **cyrillic** files for weights **400, 500, and 600** from `resources/js/app.ts`. Set `--font-sans` in `resources/css/app.css` to `"IBM Plex Sans", sans-serif` only (not Inter, Roboto, Arial, or a system-ui stack as the primary family). Test first in `resources/js/__tests__/themeTokens.spec.ts` (or extend it in T006): `--font-sans` primary is IBM Plex Sans with generic `sans-serif` fallback; Instrument Sans is gone.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared theme tokens, New York primitives, and app chrome. No user story restyle can begin until this phase is complete.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T006 Keep `@import "tailwindcss"` in `resources/css/app.css`. Import `tw-animate-css` as required by shadcn-vue Tailwind v4 New York. Define semantic tokens on `:root` by **aliasing** Tailwind v4 default palette colours from [constraints.md](./constraints.md) (research R2): `--background` → `slate-50` (App chrome background); `--card` / panel → white (Panel surface); `--foreground` → `slate-900` (Primary text); `--muted-foreground` → `slate-500` (Muted text); `--primary` and `--ring` → `blue-600` (Accent); `--destructive` → `red-600` (Danger); custom `--success` → `emerald-600` (Success). Set `--radius` to `0.5rem` (8 px). After any CLI default CSS, **overwrite** zinc/neutral primary and `0.625rem` radius. Do **not** add a `.dark` block, `dark:` utilities, a theme switcher, or `@custom-variant dark` that would apply dark styles by default. Do not use glow or oversized soft shadows. Extend `resources/js/__tests__/themeTokens.spec.ts`: tokens match those mappings; no default-applied `.dark` rules; `--radius` is `0.5rem`.
- [x] T007 [P] Copy shadcn-vue New York **Button** into `resources/js/components/ui/button/` (research R1, R3; [contracts/primitives.md](./contracts/primitives.md)). Variants: `default` / primary → accent fill, white label; `secondary` or `outline` → bordered, not accent fill; `destructive` → danger fill or danger border+text. Radius from `--radius`. No `rounded-full`. Focus: `focus-visible:ring-2 focus-visible:ring-ring`. Forward `data-testid` and native button attrs onto the `<button>`. Test first in `resources/js/components/ui/__tests__/button.spec.ts`: default/destructive/outline variants exist; classes include `focus-visible` and ring token; no `rounded-full`.
- [x] T008 [P] Copy New York **Input** into `resources/js/components/ui/input/`. Native `<input>` for text, email, password, search, number, and time. Forward `data-testid` onto the input. Same radius and `focus-visible` ring as Button. Test first in `resources/js/components/ui/__tests__/input.spec.ts`: renders `HTMLInputElement`; `data-testid` lands on the input; focus-visible ring present; no `rounded-full`.
- [x] T009 [P] Copy New York **Label** into `resources/js/components/ui/label/`. Native `<label>`. Pages keep existing locale text via `t()`. Test first in `resources/js/components/ui/__tests__/label.spec.ts`: renders a `<label>` that associates with a control.
- [x] T010 [P] Copy New York **NativeSelect** into `resources/js/components/ui/native-select/`. Must remain a native `<select>` with `<option>` children (not Reka Select). Chevron may use `@lucide/vue` (or the single lucide package the copied file imports). Forward `data-testid` onto the `<select>`, not a wrapper. Test first in `resources/js/components/ui/__tests__/native-select.spec.ts`: element is `HTMLSelectElement`; `findAll('option')` works; `data-testid` is on the select; focus-visible ring present.
- [x] T011 [P] Copy New York **Table** (table / thead / tbody / tr / th / td) into `resources/js/components/ui/table/`. Existing row `data-testid` values stay on `<tr>`. No extra interactive buttons. Test first in `resources/js/components/ui/__tests__/table.spec.ts`: semantic table markup; row testid on `tr`.
- [x] T012 [P] Copy New York **Alert** into `resources/js/components/ui/alert/` for page-level messages (sign-in failure, invitation invalid) when not a field error. Destructive/error treatment uses danger colour. Test first in `resources/js/components/ui/__tests__/alert.spec.ts`: destructive variant uses danger token; description content is visible.
- [x] T013 [P] Copy New York **Badge** into `resources/js/components/ui/badge/`. Success colour (`emerald-600` / `--success`) iff the label is Active; deactivated/revoked must not use success. Test first in `resources/js/components/ui/__tests__/badge.spec.ts`: success variant present; default/secondary is not success.
- [x] T014 [P] Add a shared native checkbox class string (not Reka Checkbox) in `resources/js/components/ui/checkbox.ts` for working days and break deducted. Native `<input type="checkbox">` with `focus-visible` ring using `--ring`. Test first in `resources/js/components/ui/__tests__/checkbox.spec.ts`: class string includes focus-visible ring; no `rounded-full` pill.
- [x] T015 Restyle `resources/js/App.vue`: app chrome `bg-background` / `text-foreground` (slate-50 / slate-900); signed-in layout is sidebar + top bar + white `<main>` panel (`bg-card`, border, `--radius`, no heavy shadow). Guest routes omit top bar and sidebar when `route.meta.guest` is true. Test first in `resources/js/__tests__/App.spec.ts`: signed-in route renders header and sidebar; guest route does not; root uses background token classes, not `bg-white text-zinc-900`.
- [x] T016 Restyle `resources/js/components/AppHeader.vue` as the compact top bar: menu toggle (`sidebar-toggle`), email, selected company name or prompt, sign-out Button (`data-testid="sign-out"` on the `<button>`). Move primary `RouterLink`s into `resources/js/components/AppSidebar.vue` with the same visibility rules (`hasUsableSelection`, role checks). Selected company name uses primary text (`slate-900`), `truncate`, and `title` = the raw company name. Prompt stays `[data-testid="select-company-prompt"]`. Sidebar toggles via `useAppSidebar` (open by default from `md` up; closed overlay below `md`; closes on route change / backdrop / Escape on mobile). Add locale keys `nav.menu`, `nav.openMenu`, `nav.closeMenu` in en+ru. Keep `resources/js/components/__tests__/AppHeader.spec.ts` asserting the same nav testids and hrefs ([contracts/test-hooks.md](./contracts/test-hooks.md)). Add truncate/`title` and sidebar toggle cases.

**Checkpoint**: Foundation ready — theme, primitives, and shell exist. User story page restyles can begin.

---

## Phase 3: User Story 1 - Company admin works in a consistent, calm interface (Priority: P1) 🎯 MVP

**Goal**: Company users, invitations, and company profile / working-day settings share the same themed buttons, fields, selects, tables, inline errors, Active success labels, and destructive deactivate/revoke actions.

**Independent Test**: Sign in as a company admin, open `/company/users` and `/company`, and compare primary buttons, text fields, dropdowns, tables, and inline errors. Trigger a validation error on each page. Viewer profile stays read-only. Every action still does what it did before.

### Tests for User Story 1

> Write / extend these tests FIRST, ensure they FAIL on unstyled pages, then restyle.

- [x] T017 [P] [US1] Extend `resources/js/pages/__tests__/CompanyUsersPage.spec.ts`: keep invite 422 inline on `[data-testid="invite-error"]`, revoke/deactivate behaviour, `invite-role` as `<select>` + options, `user-role-*` as `HTMLSelectElement`, empty-invitations `findAll('button')` testid list, no `delete-user`, no `super_admin` option. Add assertions that Active uses Badge success treatment, deactivate/revoke use Button `destructive`, invite submit uses default/primary, resend/pagination/reactivate use outline/secondary, and field errors use danger colour. Compare copy with `t()`.
- [x] T018 [P] [US1] Extend `resources/js/pages/__tests__/CompanyProfilePage.spec.ts`: keep working-day 422 inline on existing `*-error` testids, viewer `company-profile-readonly` with disabled `[data-testid="break-deducted"]` checkbox, and all profile/settings testids. Add assertions that save buttons are primary, time zone is native `<select>`, working-day and break-deducted checkboxes use the shared checkbox classes, and inline errors use danger colour.

### Implementation for User Story 1

- [x] T019 [P] [US1] Restyle `resources/js/pages/CompanyUsersPage.vue` to shared primitives (Button, Input, Label, NativeSelect, Table, field-error `<p>` with danger colour, Badge for Active / not-success for deactivated). Replace per-page `rounded bg-zinc-900` / `border-zinc-300` utilities. Primary: invite. Outline/secondary: resend, reactivate, pagination. Destructive: revoke, deactivate. Keep script, API calls, and `data-testid` on native elements. Do not add extra buttons when invitations are empty. (depends on T007–T014, T017)
- [x] T020 [P] [US1] Restyle `resources/js/pages/CompanyProfilePage.vue` the same way: profile + working-day forms for company admin; viewer read-only block unchanged in behaviour. Native checkboxes with shared classes on `working-day-*` and `break-deducted`. NativeSelect for `company-time-zone`. Inline errors stay on existing `*-error` testids. No new locale strings. (depends on T007–T014, T018)

**Checkpoint**: US1 is independently testable on company users and profile.

---

## Phase 4: User Story 2 - Super admin selects a company and sees it in the header immediately (Priority: P1)

**Goal**: `/companies` uses the new theme. After Select, the header shows the company name and company-data links without a full page reload. The selected table row is marked lightly **in addition to** header feedback (plan assumption; FR-014 MAY).

**Independent Test**: Super admin with no selection opens `/companies`: themed page, header prompt, no company-data links. Press Select: header name + company users/profile links appear without remounting the document. Clear / rejected selection restores the prompt and hides links.

### Tests for User Story 2

- [x] T021 [US2] Keep `resources/js/pages/__tests__/CompaniesPage.spec.ts` select/clear store behaviour, all company testids ([contracts/test-hooks.md](./contracts/test-hooks.md) Companies), and no `delete-company*` control. Add assertion that the selected `tr[data-testid="company-row"]` gets a light selected background (`bg-slate-100` or token equivalent) and that header feedback is not removed. Create `resources/js/__tests__/selectCompanyHeader.spec.ts`: mount `App.vue` with `/companies`, click Select, assert `[data-testid="selected-company-name"]` and company-data links appear **without** a full document reload; clear restores `[data-testid="select-company-prompt"]` and hides those links (SC-002, research R10). Keep `resources/js/components/__tests__/AppHeader.spec.ts` name-vs-prompt and link visibility.

### Implementation for User Story 2

- [x] T022 [US2] Restyle `resources/js/pages/CompaniesPage.vue` with shared primitives. Primary: create company, invite replacement. Outline/secondary: Select, pagination, resend, clear selection, reactivate. Destructive: deactivate, revoke. NativeSelect for time zone. Tables for companies and invitations. When `company.id` equals the tab selection, mark that `tr[data-testid="company-row"]` with a light selected background; this MUST NOT replace header feedback. Keep `useSelectedCompany().setSelectedCompany` / clear as today. Replace zinc utilities. (depends on T007–T014, T016, T021)

**Checkpoint**: US1 and US2 both work independently. Header selection feedback is proven at App level.

---

## Phase 5: User Story 3 - Guests see the same visual identity on sign-in and invitation pages (Priority: P2)

**Goal**: Sign-in, forgot password, reset password, and invitation acceptance use the same typeface, accent, fields, buttons, and inline errors as signed-in screens. Signed-in header stays hidden.

**Independent Test**: Signed out, open each guest page and submit once with an invalid value. Typeface, accent, fields, buttons, and danger errors match the operator screens. Each flow still works as before.

### Tests for User Story 3

- [x] T023 [P] [US3] Keep behaviour in `resources/js/pages/__tests__/SignInPage.spec.ts`, `ForgotPasswordPage.spec.ts`, `ResetPasswordPage.spec.ts`, and `AcceptInvitationPage.spec.ts`: existing testids, `HTMLInputElement` for email/password, failure/confirmation messages. Add that primary submits use default Button, fields use Input, page-level errors use Alert or danger text, and `App.spec.ts` (T015) still hides the header on `meta.guest` routes. Compare copy with `t()`.

### Implementation for User Story 3

- [x] T024 [P] [US3] Restyle `resources/js/pages/SignInPage.vue` with Label, Input, default Button (`data-testid="sign-in"` on the `<button>`), accent `RouterLink` for forgot-password, and danger treatment for `[data-testid="sign-in-error"]`. Remove `bg-zinc-900` / `border-zinc-300`. Keep script and auth API calls. (depends on T007–T009, T012, T023)
- [x] T025 [P] [US3] Restyle `resources/js/pages/ForgotPasswordPage.vue` the same way (`send-reset-link`, `reset-confirmation`, `email-error`). (depends on T007–T009, T012, T023)
- [x] T026 [P] [US3] Restyle `resources/js/pages/ResetPasswordPage.vue` the same way (`reset-password`, `password-error`, `reset-token-error`, `request-new-link`). (depends on T007–T009, T012, T023)
- [x] T027 [P] [US3] Restyle `resources/js/pages/AcceptInvitationPage.vue` the same way (`invitation-email`, `invitation-role`, `invitation-invalid`, `accept-invitation`). Role display stays as today (not a new select unless one already exists). (depends on T007–T009, T012, T023)

**Checkpoint**: Guest pages match the product identity. Header remains hidden.

---

## Phase 6: User Story 4 - Keyboard users can always see where focus is (Priority: P2)

**Goal**: Every interactive control on in-scope screens shows a visible accent-coloured (`blue-600` / `--ring`) focus indicator. Destructive actions keep the ring and stay visually destructive. No control removes focus without a visible replacement.

**Independent Test**: On each screen in scope, tab through every interactive control; each shows a visible accent focus indicator that is not hidden or clipped.

### Tests for User Story 4

- [x] T028 [US4] Add `resources/js/__tests__/focusContract.spec.ts` (research R8, quickstart US4): Button default, destructive, and outline; Input; NativeSelect; checkbox class string; AppHeader links and sign-out — class lists include `focus-visible` plus the ring token. Scan in-scope SFCs (`resources/js/App.vue`, `resources/js/components/AppHeader.vue`, `resources/js/pages/{SignIn,ForgotPassword,ResetPassword,AcceptInvitation,Home,Companies,CompanyUsers,CompanyProfile}Page.vue`) for bare `outline-none` / `focus:outline-none` without a ring replacement. jsdom need not assert computed pixels.

**Checkpoint**: US4 is independently testable via class contracts and the forbidden-outline scan. Primitive implementation landed in Phase 2.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Remaining in-scope screen, forbidden-treatment scan, localization check, and quality gate.

- [x] T029 [P] Restyle `resources/js/pages/HomePage.vue` with theme type (`text-foreground`) for `[data-testid="home-placeholder"]`. Keep redirect behaviour. Do not restyle `resources/js/components/HealthBadge.vue`. If a Home spec exists or is needed for the testid, keep `t('home.placeholder')`.
- [x] T030 [P] Add `resources/js/__tests__/themeForbidden.spec.ts` (SC-007, research R10): in-scope Vue/CSS has no `rounded-full` on controls; no purple gradient utilities; no Instrument Sans / Inter / Roboto / Arial as `--font-sans` primary; no `bg-zinc-900` primary buttons; no default-applied dark theme. In-scope files: `resources/css/app.css` and the SFCs listed in T028.
- [x] T031 Confirm no new hard-coded user-facing strings in restyled components (SC-006, FR-019). If T016–T029 introduced an accessible name that is not already on screen, add the same key to `resources/js/locales/en.json` and `resources/js/locales/ru.json`. Otherwise leave locale files unchanged. Existing keys must still resolve in both languages.
- [x] T032 Run `npx vue-tsc --noEmit`, `npm run test:run`, then `make verify` (SC-008, research R12). Do not modify Pest files under `tests/`, `public/swagger.yaml`, or PHP under `app/`. Fix only frontend failures caused by this restyle in `resources/js/**` and `resources/css/app.css`.
- [x] T033 Confirm the signed-in shell matches FR-009, FR-010, and FR-024: left collapsible sidebar (`AppSidebar.vue`), top-bar menu toggle, mobile overlay drawer (backdrop + Escape + close on navigate), desktop in-flow panel; guest routes still hide chrome. Update `contracts/test-hooks.md` shell hooks and keep Select → name/links proof in `selectCompanyHeader.spec.ts` (links may resolve from the sidebar).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational
- **User Story 2 (Phase 4)**: Depends on Foundational (uses the same primitives + themed header). Independently testable from US1
- **User Story 3 (Phase 5)**: Depends on Foundational. Independently testable from US1/US2
- **User Story 4 (Phase 6)**: Depends on Foundational primitives (focus styles). Independently testable via class contracts; after page restyles it also scans those SFCs
- **Polish (Phase 7)**: Depends on the user stories intended to ship

### User Story Dependencies

- **User Story 1 (P1)**: After Foundational — no dependency on other stories. MVP.
- **User Story 2 (P1)**: After Foundational — companies page + App-level Select test; header already themed in T016
- **User Story 3 (P2)**: After Foundational — guest pages only
- **User Story 4 (P2)**: After Foundational (primitive rings). Scan of page SFCs is strongest after US1–US3 restyles; the primitive contract can run as soon as T007–T014 exist

### Within Each User Story

- Tests MUST be written and FAIL before page restyle
- Primitives before pages
- Shell (App / header) before App-level Select test
- Native `data-testid` placement before updating existing specs that query wrappers

### Parallel Opportunities

- T002 and T003 can run in parallel with T001
- T007–T014 can run in parallel after T004 and T006
- T017 and T018 (US1 tests) in parallel; T019 and T020 (US1 pages) in parallel after their tests
- T024–T027 (US3 pages) in parallel after T023
- T029 and T030 in parallel during Polish

---

## Parallel Example: User Story 1

```bash
# Tests together:
Task: "Extend CompanyUsersPage.spec.ts theme/destructive/Active assertions"
Task: "Extend CompanyProfilePage.spec.ts theme/checkbox/error assertions"

# Pages together after tests and primitives:
Task: "Restyle CompanyUsersPage.vue with primitives"
Task: "Restyle CompanyProfilePage.vue with primitives"
```

## Parallel Example: User Story 3

```bash
# Guest pages together after T023:
Task: "Restyle SignInPage.vue"
Task: "Restyle ForgotPasswordPage.vue"
Task: "Restyle ResetPasswordPage.vue"
Task: "Restyle AcceptInvitationPage.vue"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: company admin users + profile look and behave consistently
5. Then US2 (header Select proof) before calling the super-admin flow done

### Incremental Delivery

1. Setup + Foundational → theme and primitives ready
2. US1 → operator consistency (MVP)
3. US2 → super admin Select → header (mandatory header feedback)
4. US3 → guest identity
5. US4 → focus contracts
6. Polish → Home, forbidden scan, `make verify`

### Parallel Team Strategy

1. Team completes Setup + Foundational together
2. Then: Developer A US1 pages; Developer B US2 companies + App spec; Developer C US3 guest pages
3. US4 scan after those SFCs exist (or primitive-only scan earlier)

---

## Notes

- [P] tasks = different files, no dependencies between those [P] tasks
- [Story] label maps the task to spec user stories US1–US4
- Do not keep sample tasks from the template — this file is the executable list
- Commit after each task or logical group only if the operator asks; this command does not run git
- Stop at any checkpoint to validate the story independently
- Avoid: zinc one-off classes on pages, Reka Select/Checkbox, second UI libraries, backend edits, dark mode, custom logo
