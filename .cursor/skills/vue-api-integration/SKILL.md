---
name: vue-api-integration
description: Add or change Vue 3 SPA features that call the Laravel API (typed service modules, pages/components, stores/composables, router flows, and UI states). Use when building frontend features under resources/js that consume API data or need repo-specific Vue architecture guidance.
---

# Vue 3 SPA + Laravel API Integration

## Use this skill when

- You are building or changing Vue pages/components that call the Laravel API
- You are updating the typed frontend API service layer in `resources/js/api/**`
- You are changing frontend response typing, composables, stores, or router flows tied to API data

## Do not use this skill when

- You are changing backend endpoint wiring or validation without frontend changes
- You are only changing Tailwind styling with no API integration impact
- You are redesigning auth strategy across the app unless the task explicitly asks for auth migration

## Related skills

- Backend endpoint work: `.cursor/skills/laravel-api-feature/SKILL.md`
- Security-sensitive auth/uploads: `.cursor/skills/laravel-security/SKILL.md`
- Tailwind styling: `.cursor/skills/tailwindcss-development/SKILL.md`

## SPA Architecture

The frontend is a Vue 3 SPA (Vite + TypeScript, Pinia, Vue Router) that communicates exclusively with the Laravel API.

**Architecture:**
- Laravel serves the SPA shell and exposes only JSON APIs—no Blade for dynamic content
- Vue Router owns all client routes
- Laravel handles API endpoints and initial SPA boot

**Authentication:**
- Current repo pattern: login returns a Sanctum personal access token and the SPA stores it for subsequent Bearer-authenticated requests
- Use the shared client in `resources/js/api/client.ts` for token handling and 401 redirects
- Do not migrate to cookie + CSRF auth unless the task explicitly asks to change auth strategy

**API Requirements:**
- Endpoints MUST be versioned (e.g., `/api/v1/*`)
- Return API Resources only—never raw models

**Frontend Types:**
- TypeScript types MUST mirror API Resource shapes
- Follow `data/meta/links` structure, `snake_case` payloads

**HTTP Client:**
- Centralized Axios client with auth/error interception in `resources/js/api/client.ts`
- Service layer in `resources/js/api/` wraps Axios and returns typed data
- Only `resources/js/api/**` should make HTTP requests or define endpoint paths
- Pages, components, composables, and stores may consume typed service functions from `resources/js/api/**` based on local scope

## Defaults (match this repo)

- Vue 3 SFCs use `<script setup lang="ts">`.
- API service layer lives in `resources/js/api/**` and uses `resources/js/api/client.ts`.
- Pages live in `resources/js/pages/**`; routing in `resources/js/router/index.ts`.
- Shared TS types live either next to the API module (e.g. `resources/js/api/campaigns.ts`) or in `resources/js/types/**` when shared broadly.
- Payloads and API responses use snake_case keys to match backend API Resources.
- User-facing copy should follow existing i18n usage with `@/utils/i18n` rather than introducing new hardcoded strings.

## Workflow

### 1) Confirm the backend contract first

- Endpoint path under `/api/v1/...`
- Request payload keys (snake_case)
- Response shape (`{ data: ... }`, `{ data, meta, links }`, etc.)
- Expected error shapes (422 validation, 401 unauthenticated, 403 forbidden)

### 2) Add/update the typed API function (service layer)

- Put API calls in `resources/js/api/<domain>.ts`.
- Use `apiClient.get/post/put/patch/delete<T>()` and return `response.data`.
- Keep param names snake_case when sending query params (see reports API patterns).
- For uploads, use `FormData` and `multipart/form-data` (see `resources/js/api/campaigns.ts` import example).

### 3) Add/update TS types

- Define interfaces/types that mirror API Resource shapes exactly (including snake_case).
- Prefer small, composable types; reuse shared types across modules when appropriate.

### 4) Put state in the right place

- Keep page-only fetch state, loading flags, filters, and form state local to the page or component by default.
- Use a composable in `resources/js/composables/**` when async logic or derived state is reused by multiple consumers.
- Use a Pinia store in `resources/js/stores/**` for cross-route state, session/auth state, or shared filters that multiple pages depend on.
- Do not promote page-local state into Pinia without a clear reuse need.
- Pages/components/stores/composables may call typed service functions from `resources/js/api/**`, but they should not create raw Axios calls or inline endpoint URLs.

### 5) Build the UI

- Keep SFCs single-responsibility. Extract a child component or composable when a section has a clear standalone responsibility or the file becomes hard to scan.
- Avoid inline functions in templates; prefer computed values and named handlers.
- Use Tailwind v4 classes and follow neighboring screens before inventing a new layout/style pattern.

#### Phone Input Field Pattern

Phone inputs MUST use the `usePhoneMask()` composable to enforce E.164 format (`+XXXXXXXXXXX`):

```vue
<script setup>
import { usePhoneMask } from '@/composables/usePhoneMask'

const { maskedPhone, e164Phone, onPhoneInput } = usePhoneMask()

function handlePhoneInput(event: Event): void {
    const target = event.target as HTMLInputElement
    onPhoneInput(target.value)
    target.value = maskedPhone.value  // Force mask display: +7 (904) 333-33-33
    form.value.phone = e164Phone.value || null  // Store: +79043333333
    clearError('phone')
}
</script>

<template>
    <input
        :value="maskedPhone"
        type="tel"
        inputmode="tel"
        @input="handlePhoneInput"
        placeholder="+7 (___) ___-__-__"
    />
</template>
```

**Requirements:**
- Display format: `+7 (904) 333-33-33` (with mask)
- Storage format: `+79043333333` (E.164, no spaces/punctuation)
- Invalid characters are immediately stripped from input
- Backend validates with regex: `/^\+[0-9]{8,15}$/`

### 6) Follow repo UI conventions

- Prefer `AuthenticatedLayout` for authenticated pages that follow the existing app shell.
- Reuse existing typed API modules and nearby UI patterns before introducing a new abstraction.
- Keep loading, empty, and error states explicit for data-driven screens.
- Prefer localized user-facing strings through `t(...)` when the surrounding feature already uses translations.

### 7) Add/update frontend tests for critical Vue behavior

- When changing Vue SPA behavior, add or update Vitest specs for critical client-side logic, not just backend tests.
- Use the repo test stack: `vitest`, `@vue/test-utils`, `@testing-library/vue`, and `jsdom`.
- Prioritize tests for stores, router guards, composables, and page-level workflows with branching behavior.
- Mock API modules directly (`resources/js/api/**`) instead of hitting the backend.
- Focus first on critical flows such as auth redirects, role checks, form payload normalization, validation error rendering, and persisted filter state.
- Avoid broad snapshot coverage and low-value presentational tests in the first pass.
- For frontend changes under `resources/js/**`, default verification is:
  - `npm run test:run`
  - `npx vue-tsc --noEmit`
  - check IDE diagnostics / `ReadLints`
- Also run `npm run build` when the change affects frontend tooling or app boot paths such as `package.json`, `vite.config.ts`, `resources/js/app.ts`, or router/bootstrap wiring.

## Error handling expectations

- 422: display field errors inline when possible.
- 401: follow existing global behavior in `resources/js/api/client.ts` (do not invent a new pattern without migration intent).
- 500/network: follow the existing local page/store error pattern unless the feature already has a shared notification mechanism.

## Guardrails

- Do not hardcode API base URLs; use the shared client.
- Do not “fix” auth strategy as part of unrelated feature work unless explicitly requested (this repo currently stores a token in `resources/js/api/client.ts`).
- Do not replace local page state with a store or composable unless the change actually improves reuse or coordination.
- After adding frontend tests, run the targeted Vitest suite and `npx vue-tsc --noEmit` before finishing.
