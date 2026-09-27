---
name: frontend-patterns
description: Apply the project's established Vue frontend patterns for thin components, business forms, query-string state, async search/list behavior, localization, and shared UI reuse. Use when building or refactoring SPA pages, components, composables, or list/filter workflows under `resources/js/**`.
---

# Frontend Patterns

## Use this skill when

- You are building or refactoring Vue SPA pages or components under `resources/js/**`
- You are implementing or changing business forms
- You are working on list pages with filters, pagination, or query-string state
- You are changing debounced search, remote selects, or async list loading
- You are deciding whether logic belongs in a component or a composable

## Do not use this skill when

- The task is backend-only and does not affect the Vue SPA
- The task is purely Tailwind styling with no change to component structure or frontend behavior
- The task is limited to tests with no production frontend changes

## Related skills

- Vue API integration: `.claude/skills/vue-api-integration/SKILL.md`
- Tailwind styling: `.claude/skills/tailwindcss-development/SKILL.md`
- Quality gates: `.claude/skills/quality-gates/SKILL.md`

## Thin Components First

- Keep Vue components thin by default.
- The Vue component should hold view and presentation wiring.
- Move orchestration, async flows, data shaping, and business logic into a composable.
- Keep obviously local presentational helpers in the component unless reuse or clarity justifies extraction.

Use this split when the component starts to own:

- multiple watchers
- async request flows
- validation and submit orchestration
- route/query synchronization
- non-trivial derived state that is not purely presentational

## Business Forms

For business forms, use the shared form layer in `resources/js/composables/shared/`.

### Rules

- Use `useForm` / `useField` by default instead of ad hoc refs and manual error maps.
- Write cross-field validation through `validator(value, form)`.
- Keep the submit contract consistent:
  - submit button disabled when `!form.valid`
  - submit handler returns early when `!form.valid`
- Map backend validation to form state through `setFormErrors()` or field-level `setError()`.
- Do not force backend error visibility with artificial `blur()` calls.

### Practical boundary

- Use `useForm` for real business forms and modal forms.
- Keep very small inline row-editing or field-builder flows manual if a full form abstraction adds more structure than value.

## Query String State

For page-level list screens with filters or pagination, use the shared query-state layer in `resources/js/composables/shared/`.

### Rules

- Sync page filter and pagination state with `route.query`.
- Reuse `useManagedQueryParams` and `watchManagedQueryState`.
- Normalize query state consistently:
  - omit `page=1`
  - omit empty filters
  - omit default enum values such as `all`
- Reload and browser back/forward navigation must restore the same filtered view.

### Expected pattern

Define one query-state config per screen and reuse it for:

- initial state from URL
- state to URL sync
- route watcher for external query changes

## Async Search And Lists

For debounced input or overlapping async requests, use the shared async patterns in `resources/js/composables/shared/`.

### Rules

- Use `lodash/debounce` for debounced user input.
- Do not introduce new hand-written debounce flows with `setTimeout`.
- Use `AbortController` for overlapping async list/search requests.
- Only the latest request may update loading, error, or data state.
- Aborted or stale responses must not overwrite current UI state.

## Localization

- Do not hardcode user-facing copy in `components`, `api`, `composables`, `pages`, `layouts`, or `router`.
- Route visible UI text through `t(...)` and locale keys.
- Move Russian literals into `resources/js/locales/ru.json`.
- Do not treat parser tokens, enum values, query keys, or console/debug messages as localization targets unless the feature explicitly requires it.

## Shared Frontend Reuse

Before introducing a new wrapper or helper, check:

- `resources/js/components/shared/`
- `resources/js/composables/shared/`

Prefer extending an existing shared pattern over creating a parallel abstraction with the same shape.

## Workflow

### 1) Decide the responsibility split

- Keep rendering and binding in the component.
- Move non-visual orchestration into a composable when the script block stops being straightforward.

### 2) Pick the right state pattern

- Business form: shared form composables
- List filters/pagination: shared query-state composables
- Debounced remote search: `lodash/debounce` + `AbortController`

### 3) Reuse before inventing

- Check shared UI and shared composables first.
- Match nearby page and component conventions before adding a new abstraction.

### 4) Keep the user contract stable

- Invalid forms must not submit.
- URL-backed filters must restore screen state.
- Latest async request must win.
- Visible UI copy must come from locale keys.
