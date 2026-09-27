---
name: http-endpoint-layer
description: Implement the HTTP layer for a new or changed Laravel API v1 endpoint while delegating business logic to Actions and Services. Use when adding or changing controllers, routes, request validation wiring, API Resources, endpoint auth middleware, or HTTP-layer guard behavior.
---

# HTTP Endpoint Layer (Laravel API v1)

## Use this skill when

- You are changing routes, controllers, resources, or endpoint middleware
- Business logic already exists and the main work is HTTP wiring
- You need a thinner slice than the full end-to-end API feature workflow

## Do not use this skill when

- You are building a full endpoint from scratch and also changing Action/Service logic, tests, and OpenAPI
- You are only changing validation rules or DTO parsing inside a Form Request
- You are only changing business logic behind an existing endpoint

## Related skills

- End-to-end API feature: `.cursor/skills/laravel-api-feature/SKILL.md`
- Request validation: `.cursor/skills/http-request-validation/SKILL.md`
- Action layer: `.cursor/skills/action-layer/SKILL.md`
- Exception workflow: `.cursor/skills/laravel-exception-handling/SKILL.md`

## References

- Canonical non-negotiables: `.specify/memory/constitution.md`
- Always-on repo summary: `.cursorrules`
- Full end-to-end playbook (if you need everything): `.cursor/skills/laravel-api-feature/SKILL.md`
- Request validation playbook: `.cursor/skills/http-request-validation/SKILL.md`

## Goal

Create/modify the **route + controller + request validation + resource response** so the endpoint is correct, thin, and delegates business logic to the Action/Service layer.

## Scope (HTTP wiring only)

- Routes: `routes/api.php`
- Controllers: `app/Http/Controllers/Api/V1/**`
- Requests: `app/Http/Requests/**`
- Resources: `app/Http/Resources/**`
- Middleware (when endpoint auth/throttle changes): `app/Http/Middleware/**`

## Workflow

### 1) Define the contract

- HTTP method + path under `/api/v1/...`
- Auth: public vs `auth:sanctum` vs role/policy requirements
- Request payload keys (snake_case)
- Response envelope (`{ data: ... }`, `{ data, meta, links }`, etc.)
- Errors to support (at least 401/403/404/422 as applicable)

### 2) Add/update the route

- Edit `routes/api.php` under `Route::prefix('v1')`.
- Prefer named routes consistent with existing patterns.
- Add middleware (`auth:sanctum`, throttling) here, not inside Actions.

### 3) Create the invokable controller (thin)

- Location: `app/Http/Controllers/Api/V1/<Domain>/<Something>Controller.php`
- Signature pattern:
  - typed `FormRequest` (or `Request` when unavoidable)
  - Action/Service injected as parameter or constructor
- Controller responsibilities:
  - authorize via Form Request / policy
  - call Action/Service with DTO/primitives
  - return Resource directly (200) or `->response()->setStatusCode(...)` (non-200)

#### Controller exception boundary

- Follow `.cursor/skills/laravel-exception-handling/SKILL.md` for guard-clause style and exception selection.
- Keep controller guards near the top of the method, immediately after loading the user or route resource.
- Controllers may use HTTP-layer exceptions, but domain failures should stay in the Action layer.

### 4) Create/update the Form Request (validation + DTO)

- Follow the dedicated Request playbook: `.cursor/skills/http-request-validation/SKILL.md`

### 5) Return the correct Resource shape

- Use `app/Http/Resources/**` and snake_case keys.
- Eager load relations/counts in controller before creating the resource (avoid N+1 surprises).
- Keep API Resource responses wrapped. Only `OkResource` may set `public static $wrap = null`; every other `JsonResource` should use the default wrapper so responses stay under `{ data: ... }` (plus `meta` / `links` for collections when applicable).

#### Resource boundary (important)

Resources are the **presentation layer** — they own all logic whose sole purpose is shaping data for the API consumer.

**Resources MUST handle:**
- Field selection, snake_case mapping, null/default handling, conditional inclusion
- Value formatting: date formatting (`->toIso8601String()`, `->toDateString()`), enum `->value` extraction, currency/number formatting
- Computed display fields: convenience booleans (`has_link`, `is_read`), color codes, progress percentages, display labels
- Display ordering and primary-contact resolution when the logic is purely about how data appears in the response

**Resources MAY resolve presentation-oriented Services** (formatters, label resolvers, display builders, display-ordering helpers) inside `toArray()` when the Service shapes already-loaded data for the response without mutating domain state.

**Resources MUST NOT:**
- Resolve or invoke `Action` classes inside `toArray()`
- Resolve domain-mutation or query-building `Service` classes (Services that create/update/delete records or build queries for data retrieval)
- Perform database queries, trigger lazy loading, dispatch jobs/events, write cache, log, or call external services
- Contain business rules or domain decisions — only presentation decisions

**Upstream responsibility:** Controllers or upstream Actions must eager-load relations/counts and prepare all domain data needed by the Resource before serialization.

**Litmus test:** does this Service change domain state or make domain decisions, or does it just shape already-loaded data for the response? If the latter, it is a presentation concern and belongs in (or is called from) the Resource.

#### Standardized Response Formats

Canonical patterns for API responses (other skills should **link here** instead of copying this block).

**1) Delete**

Use **200 OK** with a translated `message` via `OkResource` when the SPA shows confirmation copy. `OkResource` is the only API Resource that may use `$wrap = null`, so the response stays `{ "message": "..." }` (no `data` envelope). Do **not** use **204 No Content** with `->json([...])`—204 must not include a body.

```php
return new OkResource(message: trans('success.campaign_deleted'));
```

**2) List**

```php
return CampaignResource::collection(
    // Action/Service call returning collection
);
```

**3) Show**

```php
return new CampaignResource($campaign->load(['type', 'managers', 'products'])->loadCount('leads'));
```

**4) Store**

```php
use Illuminate\Http\Response;

return new CampaignResource($campaign->load(['type', 'managers'])->loadCount('leads'))
    ->response()
    ->setStatusCode(Response::HTTP_CREATED);
```

**5) Update**

```php
return new CampaignResource($campaign->load(['type', 'managers'])->loadCount('leads'));
```

**6) Command / Operation Result (non-model)**

When a controller calls an Action that returns operational metadata (counts, status flags, etc.) instead of a model, still use a dedicated `JsonResource`. Accept scalar/array data via the constructor and wrap it consistently under `{ data: ... }`; do not set `$wrap = null`. Follow the `VersionResource` pattern.

```php
return new MarkAllNotificationsReadResource(markedCount: $marked);
```

### 6) Hand-off to Action layer

- If business logic is missing, stop and create/extend the Action/Service (see `action-layer` skill).

