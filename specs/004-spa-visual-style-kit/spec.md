# Feature Specification: SPA Visual Style Kit

**Feature Branch**: `factory/specify`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "SPA visual style kit. Restyle the Vue 3 SPA for this multi-company access-control CRM so it looks like a calm, modern B2B product UI, without changing authentication, roles, tenancy, or API behaviour. Replace the current plain white/zinc utility look with a coherent visual system that operators can trust for daily work: companies, users, invitations, company profile, working-day settings, and sign-in flows. The service stores sensitive personal and biometric data; the UI must feel precise and restrained, not like a marketing landing page. Use shadcn-vue components in the New York style on the existing Vue 3 + Tailwind CSS v4 stack, with cool slate surfaces, slate text, blue-600 accent, red-600 danger, emerald-600 success, small radius, minimal elevation, and IBM Plex Sans. Introduce a shared theme and base primitives, restyle the app shell and all existing operator screens, keep Russian and English copy via locale keys, preserve routes, roles, and behaviour, keep the selected company clearly visible in the header after a super admin selects it. Backend, auth, role and isolation rules, and API test semantics must not change. No third-party admin template, no second component library. Out of scope: new business features, dark mode toggle, mobile-native redesign, custom logo asset."

## User Scenarios & Testing *(mandatory)*

This feature changes only how the existing screens look. It builds on `specs/001-user-authentication-and-roles` (sign-in, password flows, roles, invitations), `specs/002-company-management-for-a` (companies, company profile, working day settings), and `specs/003-data-isolation-between-companies` (selected company for the super admin, header feedback). Every rule those features define about who may see or do what stays exactly as it is. The exact theme values (colours, radius, typeface) are listed in `constraints.md` and are the single source of truth for this feature.

The screens in scope are the ones that exist today:

- Sign in, forgot password, reset password (guest)
- Invitation acceptance (guest)
- Companies list and create-company form, including first admin invitation actions, deactivate, and select/clear selection (super admin)
- Company users and invitations (company admin, super admin with a selected company)
- Company profile, including working day settings (company admin, viewer, super admin with a selected company)
- Home landing text shown when no role-specific redirect applies
- The app shell: page background, content area, and header with navigation and sign-out

### User Story 1 - Company admin works in a consistent, calm interface (Priority: P1)

A company admin signs in and moves between company users, invitations, and the company profile with its working day settings. Every button, text field, dropdown, table, status label, and error message looks and behaves the same way on every page, using the shared theme.

**Why this priority**: Company admins are the daily operators. A consistent interface on their screens is the main value of the feature and can ship on its own.

**Independent Test**: Sign in as a company admin, open the company users page and the company profile page, and compare the primary buttons, text fields, dropdowns, tables, and inline errors on both. Trigger a validation error on each page. Confirm the same visual treatment is used everywhere and that every action still does what it did before.

**Acceptance Scenarios**:

1. **Given** a signed-in company admin, **When** they open the company users page and the company profile page, **Then** primary buttons, text fields, dropdowns, and tables on both pages use the same shared theme appearance (accent colour, radius, border, typeface).
2. **Given** a company admin on the company users page, **When** they submit an invitation with an invalid email, **Then** the error appears inline next to the field in the danger colour, and the invitation is not sent, exactly as before.
3. **Given** a company admin on the company profile page, **When** they save working day settings with an invalid value, **Then** the error appears inline in the danger colour and nothing is saved, exactly as before.
4. **Given** a list containing an active item, **When** the page shows its state label, **Then** the "Active" label uses the success colour and a deactivated or revoked label does not.
5. **Given** a destructive action (deactivate, revoke), **When** it is shown on any page, **Then** it uses the danger colour and is visually distinct from the primary action.
6. **Given** a signed-in viewer, **When** they open the company profile page, **Then** they see the same themed page with read-only content and no change controls, exactly as before.

---

### User Story 2 - Super admin selects a company and sees it in the header immediately (Priority: P1)

A super admin opens the companies page, which uses the new theme. They select a company. Without a full page reload, the header shows the selected company's name and the company-data links (company users, company profile) appear. The companies table may also lightly mark the selected row.

**Why this priority**: Working with the wrong company's data is the main risk for a super admin. Clear, immediate header feedback after selection is a mandatory part of the feature.

**Independent Test**: Sign in as a super admin with no selection, open the companies page, confirm the header shows the "select a company" prompt and no company-data links. Press Select on one company. Confirm, without reloading, that the header shows that company's name and the company-data links. Clear the selection and confirm the header returns to the prompt and the links disappear.

**Acceptance Scenarios**:

1. **Given** a signed-in super admin with no selected company, **When** they open the companies page, **Then** the page and header use the new theme, the header shows the "select a company" prompt, and no company-data links are shown.
2. **Given** a super admin on the companies page, **When** they press Select on a company, **Then** the header shows that company's name and the company users and company profile links appear, without a full page reload.
3. **Given** a super admin with a selected company, **When** they look at the header on any signed-in page, **Then** the selected company name is clearly visible and readable against the header background.
4. **Given** a super admin with a selected company, **When** they clear the selection or the selection is rejected, **Then** the header stops showing the company name and the company-data links disappear, exactly as before.
5. **Given** a super admin with a selected company, **When** they view the companies table, **Then** the selected row MAY be marked lightly; if marked, the marking does not replace or weaken the header feedback.

---

### User Story 3 - Guests see the same visual identity on sign-in and invitation pages (Priority: P2)

A guest opens the sign-in, forgot password, reset password, or invitation acceptance page. These pages use the same typeface, accent colour, fields, buttons, and inline errors as the signed-in screens, so the product feels like one trustworthy service from the first screen.

**Why this priority**: Guest pages are the first impression and must match the product, but they are fewer and simpler than the operator screens.

**Independent Test**: Signed out, open each guest page and submit it once with an invalid value. Confirm the typeface, accent colour, field and button styling, and inline error styling match the signed-in screens, and that each flow still works as before.

**Acceptance Scenarios**:

1. **Given** a signed-out visitor, **When** they open the sign-in page, **Then** the page uses the theme typeface, the accent colour for the primary action and links, and themed text fields.
2. **Given** a signed-out visitor on the sign-in page, **When** they submit wrong credentials, **Then** the error is shown in the danger colour and they stay signed out, exactly as before.
3. **Given** a visitor with a valid invitation link, **When** they open the invitation acceptance page, **Then** it uses the same typeface, accent colour, fields, and buttons as the sign-in page.
4. **Given** a visitor on the forgot password or reset password page, **When** they submit the form, **Then** the result and any error are shown with the same themed treatment, and the flow behaves as before.
5. **Given** any guest page, **When** it is shown, **Then** the signed-in header is not shown, exactly as before.

---

### User Story 4 - Keyboard users can always see where focus is (Priority: P2)

An operator who navigates with the keyboard tabs through header links, buttons, text fields, dropdowns, and table actions. The focused control always shows a clearly visible focus indicator in the accent colour.

**Why this priority**: Operators handle sensitive data and must know which control an action will hit. Visible focus is also a basic accessibility expectation.

**Independent Test**: On each screen in scope, tab through every interactive control and confirm each one shows a visible accent-coloured focus indicator that is not hidden or clipped.

**Acceptance Scenarios**:

1. **Given** any screen in scope, **When** the operator moves focus with the keyboard onto a button, link, text field, dropdown, or checkbox, **Then** a focus indicator in the accent colour is visible around that control.
2. **Given** a focused destructive action, **When** it receives keyboard focus, **Then** the focus indicator is still visible and the control remains distinguishable as destructive.
3. **Given** a control that previously showed a browser focus outline, **When** the theme is applied, **Then** focus is never removed without a visible replacement.

---

### Edge Cases

- A company name is long: the header shows it without breaking the header layout; the full name remains available to the operator (for example, not cut without any way to read it).
- The super admin's selection is rejected by the server while they are on a company-data page: they are redirected to the companies page and the header returns to the prompt, exactly as before, with themed styling.
- A page is loading or a request fails: existing loading and error messages appear with themed styling; no new states are introduced.
- A screen with many form errors at once (for example, the create-company form): every field error is shown inline in the danger colour next to its field.
- A narrow browser window at a width where a screen is usable today: the screen remains usable at that width after the restyle (no control becomes unreachable or overlaps another).
- The theme typeface fails to load: text still renders in a readable fallback sans-serif typeface and all screens remain usable.
- The interface language is switched between Russian and English: every themed control shows its locale text; longer Russian labels do not break buttons, header links, or table headers.
- A role sees fewer controls (viewer, company admin without super admin links): the themed layout does not leave broken gaps or empty toolbars where hidden controls would be.

## Requirements *(mandatory)*

### Functional Requirements

**Shared theme and primitives**

- **FR-001**: The SPA MUST use one shared theme that defines the colour, radius, elevation, and typeface values listed in `constraints.md`. Screens MUST take these values from the shared theme rather than restating them per screen.
- **FR-002**: The SPA MUST provide shared themed primitives for the controls existing screens use: buttons (primary, secondary, destructive), text inputs, dropdown selects, checkboxes where already used, tables, inline field errors, page-level alerts/messages, state labels, and compact header navigation.
- **FR-003**: Every screen in scope MUST use the shared primitives for those controls, so the same control looks the same on every screen.
- **FR-004**: The primary action colour, links, and focus indicators MUST use the accent colour. Destructive actions (deactivate, revoke) and validation errors MUST use the danger colour. Positive state labels such as "Active" MUST use the success colour.
- **FR-005**: Controls MUST use the small radius from `constraints.md`; pill-shaped controls MUST NOT be used.
- **FR-006**: Surfaces MUST be separated mainly by borders; heavy or oversized soft shadows and glow effects MUST NOT be used.
- **FR-007**: All UI text MUST render in the theme typeface named in `constraints.md`, with a sans-serif fallback. The excluded typefaces listed in `constraints.md` MUST NOT be the primary UI typeface.
- **FR-008**: The theme MUST NOT use any of the excluded visual treatments listed in `constraints.md` (purple-on-white gradients, warm cream paper with serif display type, terracotta accents, dark mode by default, glow effects, oversized soft shadows).

**App shell and header**

- **FR-009**: The app shell MUST use the app-chrome background and white content panels from the theme, with consistent spacing and type across all signed-in screens.
- **FR-010**: The header MUST keep every element it shows today, with the same visibility rules: current user email, the super admin's selected company name or "select a company" prompt, companies link (super admin only), company users link, company profile link, and sign-out.
- **FR-011**: For a super admin with a usable selection, the header MUST show the selected company name clearly and readably on every signed-in page.
- **FR-012**: After a super admin selects a company, the header MUST show the selected company name and the company-data links without a full page reload.
- **FR-013**: Company-data navigation (company users, company profile) MUST appear for a super admin only when a usable selection exists, exactly as today.
- **FR-014**: The companies table MAY lightly mark the currently selected company's row. If it does, the marking MUST be subtle and MUST NOT replace the header feedback.

**Screens**

- **FR-015**: The following screens MUST be restyled with the shared theme and primitives: sign in, forgot password, reset password, invitation acceptance, companies list and create-company form (including first admin invitation actions, deactivate, select, and clear selection), company users and invitations, company profile including working day settings, and the home landing text.
- **FR-016**: Guest screens MUST use the same typeface, accent colour, fields, buttons, and inline error styling as signed-in screens, and MUST continue to hide the signed-in header.
- **FR-017**: Every interactive control on every screen in scope MUST show a visible focus indicator in the accent colour when focused by keyboard. No control may remove the focus indicator without a visible replacement.
- **FR-018**: Screens MUST remain usable at every browser width at which they are usable today; the layout MAY remain desktop-first.

**Copy and localization**

- **FR-019**: All user-facing text MUST continue to come from the existing Russian and English locale keys. Any new user-facing text introduced by the restyle (for example, an accessible label) MUST be added to both Russian and English locales; no user-facing text may be hard-coded in a component.

**Behaviour that must not change**

- **FR-020**: Routes, route access rules, role rules, company isolation rules, the super admin selection flow, and the sign-in and token approach MUST behave exactly as before.
- **FR-021**: No backend endpoint, request field, response field, status code, validation rule, or API contract may change as part of this feature.
- **FR-022**: Existing frontend tests MUST keep asserting the same behaviour. They MAY be updated only where markup, classes, or visible structure changed, and the hooks they use to find elements MUST remain available so behaviour checks stay meaningful.
- **FR-023**: The visual system MUST be built from the chosen component set in the style named in `constraints.md`. No third-party admin template may replace the SPA wholesale, and no second component library may be added alongside it.

### Key Entities

- **Theme**: The shared set of visual values (surface, text, accent, danger, success colours; radius; elevation; typeface) that every screen draws from. Values are listed in `constraints.md`.
- **Themed primitive**: A reusable control (button, text input, select, checkbox, table, inline error, alert, state label, header navigation item) whose appearance comes from the theme and is identical wherever it is used.
- **Selected company indicator**: The header element that shows a super admin which company they are working with, or prompts them to select one.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of the screens listed in FR-015 use the shared theme and shared primitives; a reviewer comparing any two screens finds no control of the same kind with a different appearance.
- **SC-002**: After a super admin presses Select, the selected company name and company-data links appear in the header within 1 second and without a full page reload, in 100% of attempts.
- **SC-003**: 100% of interactive controls on the screens in scope show a visible accent-coloured focus indicator when reached by keyboard.
- **SC-004**: 0 changes to backend behaviour: every existing backend test passes without modification, and no API contract document changes.
- **SC-005**: Every existing frontend behaviour check (routes, role-based visibility, selection flow, form submission, validation display) still passes after the restyle.
- **SC-006**: 0 hard-coded user-facing strings are introduced; every visible label on the screens in scope shows correctly in both Russian and English.
- **SC-007**: 0 occurrences of the excluded visual treatments and typefaces listed in `constraints.md` on the screens in scope.
- **SC-008**: The repository quality gate passes after the restyle.

## Assumptions

- The theme typeface is served by the application itself rather than requested from a third-party font service at runtime, because the service stores sensitive personal and biometric data. If the team prefers a hosted font service, this assumption changes during planning.
- The component set and style named in `constraints.md` (shadcn-vue, New York style) and the existing Tailwind CSS v4 setup are stakeholder decisions, not open choices; adding what that component set needs is expected and will be justified in `plan.md`.
- "Screens in scope" are exactly the screens that exist today (listed at the top of User Scenarios). Pages for employees, terminals, reports, or other future features are not part of this feature.
- Existing loading, empty, and error messages keep their current wording and conditions; only their styling changes.
- Light theme only; no dark mode and no theme switcher.
- No custom logo asset; the product identity comes from typography and colour only.
- Layouts may stay desktop-first; "narrow widths already in use" means any width at which a screen is usable today.
