---
name: laravel-api-feature
description: Implement or change a Laravel API v1 endpoint end-to-end (route, invokable controller, Form Request + toDto DTO, Action/Service, API Resource, OpenAPI update, Pest tests, Larastan→Pint). Use when adding/changing /api/v1 endpoints, validation, API Resources, or API auth/authorization.
---

# Laravel API Feature (v1)

## Use this skill when

- You are implementing a new `/api/v1/...` endpoint end to end
- The change spans route, controller, validation, action/service, resource, tests, and OpenAPI
- You want one umbrella workflow instead of coordinating multiple narrower skills manually

## Do not use this skill when

- You are only changing HTTP wiring and the business logic already exists
- You are only changing a Form Request or DTO mapping
- You are only changing Action/Service logic behind an existing stable endpoint

## Related skills

- HTTP-only changes: `.cursor/skills/http-endpoint-layer/SKILL.md`
- Request validation: `.cursor/skills/http-request-validation/SKILL.md`
- Action/service logic: `.cursor/skills/action-layer/SKILL.md`
- DTOs: `.cursor/skills/spatie-data/SKILL.md`
- Finish checks: `.cursor/skills/quality-gates/SKILL.md`

## Defaults (follow existing repo conventions)

- Routes live in `routes/api.php` under `Route::prefix('v1')`.
- API controllers are invokable and live in `app/Http/Controllers/Api/V1/**`.
- Validation happens in Form Requests in `app/Http/Requests/**` with a `toDto()` method.
- Business logic lives in `app/Actions/**` or `app/Services/**` and MUST NOT depend on HTTP (no Request/Response/Session/Cookies).
- Response shape is defined by API Resources in `app/Http/Resources/**` using snake_case keys.
- OpenAPI lives in `public/swagger.yaml` and MUST be kept current.

## Workflow (use as a checklist)

### 1) Confirm the contract first

- Identify endpoint: method + path under `/api/v1/...`
- Identify auth: public / `auth:sanctum` / role policy
- Identify payload + response resource shape (snake_case)
- Identify error cases (at least 401/403/404/422 where applicable)
- Identify whether the change requires DB writes (and therefore transactions / afterCommit)

### 2) Write the Pest tests first (and make them fail)

- Feature tests belong in `tests/Feature/Api/V1/**`.
- Cover:
  - success response
  - validation failure (422)
  - auth failure (401) and/or forbidden (403) if protected
- Mock side effects:
  - external HTTP: `Http::fake()` / `Http::preventStrayRequests()`
  - storage: `Storage::fake()`
  - cache: `Cache::fake()`

### 3) Add/adjust the route (v1)

- Use named routes consistent with existing patterns (`*.index`, `*.store`, `*.show`, `*.update`, `*.destroy`).
- Keep authorization in middleware / policies; keep controller thin.

### 4) Add/adjust the Form Request (validation + DTO)

- **Reuse check**: before creating a new Form Request or DTO, search the same domain for existing ones with the same shape. Reuse or share them instead of duplicating.
- **Skip the DTO** when the Action's only input is a single model/primitive (e.g., just the authenticated user). Pass the value directly from the controller.
- Prefer a dedicated Form Request class for validation when there are actual rules to validate.
- Rules must be explicit and typed where possible (Enums for domain values; no magic strings).
- Include custom `messages()` when UX requires it (see existing requests).
- Provide an explicit `toDto()` that returns an `App\Data\**\*Data` DTO when the payload has 2+ fields.

If you are only changing request parsing/authorization rules, prefer the narrower request skill:
- `.cursor/skills/http-request-validation/SKILL.md`

### 5) Implement the Action/Service (pure)

- Accept primitives/DTOs, return primitives/data/models (no HTTP objects).
- Keep DB transactions thin:
  - prepare data outside `DB::transaction(...)`
  - do only DB writes inside the transaction
  - move side effects to `DB::afterCommit(...)`

### 6) Return an API Resource (snake_case)

- Use `JsonResource` / resource collections.
- Keep Resources wrapped under `{ data: ... }`. Do not set `$wrap = null` on any Resource except `OkResource`.
- Eager load relations and counts explicitly to avoid N+1.
- Do not return raw Eloquent models from controllers.
- **Standardized response formats** (delete 200 + `message`, store 201, list/show/update resource shapes): single source of truth is **§ Standardized Response Formats** in `.cursor/skills/http-endpoint-layer/SKILL.md`—do not duplicate that block here.

### 7) Update OpenAPI

- Update `public/swagger.yaml` for:
  - path + method
  - auth (follow the current repo auth model from `.cursor/skills/laravel-security/SKILL.md`)
  - request schema (if applicable)
  - response schema + error responses (401/403/404/422)

### 8) Run quality gates (order matters)

1. Larastan level 8:

```bash
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
```

2. Pint:

```bash
vendor/bin/pint --dirty
```

3. Targeted tests:

```bash
php artisan test --compact tests/Feature/Api/V1/<YourTest>.php
```

## References

- Canonical non-negotiables: `.specify/memory/constitution.md`
- Always-on repo summary: `.cursorrules`
- Spatie Data DTOs: `.cursor/skills/spatie-data/SKILL.md`
- HTTP Request validation: `.cursor/skills/http-request-validation/SKILL.md`
- Action layer: `.cursor/skills/action-layer/SKILL.md`