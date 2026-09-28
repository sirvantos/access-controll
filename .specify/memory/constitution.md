<!--
Sync Impact Report
==================
Version change: 2.1.0 → 2.1.1
Modified principles:
  - I.c Weak Links: the default stays a PublicApi-only import. A cross-module import of internals is legal only when the current feature's plan.md records the exception. An unrecorded exception is still rejected.
Removed sections:
  - None
Templates requiring updates:
  - None
Follow-up TODOs:
  - None
-->

# access-controll Constitution

## Core Principles

### I. Layer Separation (NON-NEGOTIABLE)

Controllers MUST remain thin and delegate all business logic to Spatie Actions, Services, or Repositories.

Actions and Services MUST be pure:
- Accept Eloquent models (for entity references), DTOs (for structured payloads), or primitives/Enums as input — prefer passing the resolved model over a bare ID when that model belongs to the same business module. A model from another module does not cross the boundary unless Section I.c records an exception; otherwise pass its identifier and use that module's `PublicApi`
- Return data/primitives
- No HTTP-specific logic (Request, Response, Session, Cookies)
- No authorization/permission checks (`$user->can()`, `Gate::allows()`, policy calls, role checks) — authorization belongs in Form Request `authorize()`, controller guards, middleware, or policies

Controllers handle HTTP concerns exclusively:
- JsonResource, redirects, status codes
- Use dependency injection and service containers

**Dependency direction (a reviewer rejects a violation):**

| From | May call | Must not call |
|------|----------|----------------|
| Controller, middleware | Form Request, Action, Service, JsonResource | Eloquent query construction, domain calculations, `env()` |
| Form Request | validation rules, `toDto()` | Actions, Services, response shaping |
| Action | same-module Service, Model, Repository, DTO, Enum; another module's `PublicApi` only | `Request`, `Response`, `Session`, `Cookie`, `JsonResource`, `Gate`, policies; another module's Model, Repository, Action, or Service |
| Service | same-module Model, Repository, DTO, Enum, Services; another module's `PublicApi` only | HTTP types, controllers, JsonResource; another module's internals |
| Repository | same-module Model, query builder | HTTP types, Actions, controllers; another module's tables |
| JsonResource | model attributes, presentation formatters | mutating Actions or Services |
| Model | casts, relations, scopes | HTTP types, Actions, Services |

A cell that forbids another module's internals yields to a recorded exception in Section I.c. Without that entry the cell stands.

Business decisions live in `App\Actions` (one use case) or `App\Services` (a capability shared by more than one use case). HTTP I/O lives in controllers and middleware. Database I/O lives in Eloquent models or repositories called by an Action or Service of the same module. External HTTP I/O lives in a dedicated client class called by an Action or Service, never in a controller.

### I.c Weak Links Between Business Modules (NON-NEGOTIABLE)

A business module is one area of business logic, not a technical layer. Examples of modules are identity, access grants, and audit. Actions, Services, and Models are layers inside a module. Controllers stay in `app/Http` and are not a module. The module name is the `{Module}` segment of `App\Modules\{Module}`.

Inside one module, calls stay as strong as Section I allows. Between modules the link MUST be weak.

**The default legal import across modules** is `App\Modules\{Other}\PublicApi\*`. Any other `use App\Modules\{Other}\...` from outside that module — Models, Repositories, Actions, Services, internal DTOs, exceptions — is rejected unless a recorded exception covers that exact import.

`PublicApi` may contain interfaces, readonly DTOs, identifier value objects, and domain events. It MUST NOT contain Eloquent models, query builders, or concrete Action classes.

**These are also rejected unless a recorded exception covers that exact use:**

- an Eloquent relation (`belongsTo`, `hasMany`, `hasOne`, `morphTo`, or a join) whose target class lives in another module;
- a query, `DB::table()`, or raw SQL in module A that names a table whose migration lives in module B;
- a method on module A that type-hints an Eloquent model from module B. The default is to pass the identifier, then call `PublicApi` when the other module's data is required.

**Recorded exception.** A stronger link is legal only when the current feature's `plan.md` contains a `Module boundary exceptions` entry with all three fields:

- the importing class;
- the concrete class, relation, or table it reaches;
- why `PublicApi` cannot express this case.

The entry covers only what it names. A missing entry, or an entry that names a different class, is not an exception. The reviewer does not grant one by taste.

HTTP controllers stay in `app/Http` and may call Actions of the module that owns the route. That call is not a cross-module link. When a request needs another module, the controller or the owning Action calls that module's `PublicApi`, unless a recorded exception says otherwise.

The rule does not apply to code that is not a business module: the framework, `app/Support`, and types no module owns.

### I.a OOP & Design Patterns (SOLID - NON-NEGOTIABLE)

**Single Responsibility**: Each class MUST have one reason to change. Actions do one thing; Services encapsulate one domain concern.
**Action vs Service selection**:
- Prefer an **Action** when the class is the named entrypoint for a single use case or command (create, update, resolve, list, build, sync, assign), especially when it owns orchestration, transaction boundaries, or is called directly from a controller, job, command, or listener.
- Prefer a **Service** when the class provides a reusable domain capability that supports multiple use cases, such as matching, normalization, token generation, calculations, or shared query-building/lookup rules.
- Do NOT use `Service` as the default bucket for endpoint-specific business logic. If a class has one public operation and exists primarily to fulfill one feature/use case, default to an Action unless it is intentionally shared as a reusable capability.
- Actions MAY depend on Services of the same module; Services SHOULD NOT become mini-controllers or carry HTTP/presentation concerns. Another module is reachable through its `PublicApi`, or through a recorded exception (Section I.c).
**Open/Closed**: Classes MUST be open for extension, closed for modification. Use interfaces and composition to extend behavior.
**Liskov Substitution**: Subtypes MUST be substitutable for their base types; never violate parent contracts.
**Interface Segregation**: Prefer many small, focused interfaces over large monolithic ones.
**Dependency Inversion**: High-level modules MUST NOT depend on low-level modules; both depend on abstractions (interfaces). Inject dependencies via constructor.
**Reuse Before Create (DRY — NON-NEGOTIABLE)**:
- Before creating any new class, helper, or pattern, search the relevant domain directory for an existing artifact with the same shape or purpose. Reuse or extend it instead of duplicating.
- Skip wrapper classes (DTOs, Form Requests) when they would contain only a single field — pass the value directly.
- Name shared artifacts by data shape, not by endpoint verb, when multiple callers use the same structure.
- See `.cursor/rules/reuse-before-create.mdc` for per-layer examples.
**Composition over Inheritance**:
- Use traits only for stateless, horizontal behavior
- Avoid deep inheritance (max 2 levels)
- Prefer interfaces over abstract classes when no shared implementation is needed
  **Value Objects vs DTOs**:
- Value Objects: immutable domain concepts (equality by attributes, no identity)
- DTOs (Spatie Laravel Data): data transfer between layers (no business logic)
  **Design Patterns**:
- Factory: complex object creation
- Strategy: interchangeable algorithms
- Decorator: dynamic behavior extension
  **Encapsulation**:
- Use private/protected properties with explicit getters
- Avoid public properties except in DTOs
- Apply Tell, Don't Ask—objects operate on their own data

**DTO mapping**: Form Requests validate input and expose an explicit `toDto()` method that returns a typed DTO using Laravel's typed request helpers. Follow `.cursor/skills/spatie-data/SKILL.md` and `.cursor/skills/http-request-validation/SKILL.md`.

### I.b Feature Discovery Conventions

For backend/API features, prefer tracing behavior from the declared route to the responsible controller, then through the request validation, DTO, action/service, and response resource layers as applicable.

For SPA features, prefer tracing behavior from the frontend router or rendered page/component, then through stores/composables and API client integrations as applicable.

These are the default discovery paths for understanding feature behavior quickly, but the actual runtime entrypoint in code takes precedence when it differs.

### II. Strict Typing & Code Quality

**Required declarations:**
- All PHP files MUST declare `declare(strict_types=1);`
- Follow PSR-12 coding standards
- Use PHP 8.4+ features: typed properties, match expressions, arrow functions, nullsafe operators

**Static Analysis (MANDATORY):**
- Larastan level 8 is required—`vendor/bin/phpstan analyse` MUST pass before merge
- Level 8 enforces: union types, method return types, parameter types, property types, stricter type comparisons

**No Magic Values:**
- NEVER use magic constants, magic strings, or magic numbers
- Use Backend Enums (PHP 8.1+ native enums) for status values, types, domain constants
- Extract numeric values to named constants or enums (file size limits, timeouts, thresholds)
- **All class constants MUST have explicit types** (PHP 8.3+): `private const int AUDIO_BITRATE = 16;`

**Minimal Variables:**
- Prioritize zero-variable expressions
- Chain Laravel facades, helpers, methods
- Favor direct returns, early returns, fluent interfaces
- Eliminate redundant/temporary variables

**Prefer Helper Functions over Facades:**
| Facade | Helper |
|--------|--------|
| `URL::route()` | `route()` |
| `Config::get()` | `config()` |
| `Response::make()` | `response()` |
| `Redirect::to()` | `redirect()` |
| `View::make()` | `view()` |
| `Abort::abort()` | `abort()` |

Helper functions are more concise, don't require imports, and follow Laravel conventions.

**Stringable over native strings (NON-NEGOTIABLE):**

When a value is a `Stringable` instance (from Form Request's `$this->string()`):
- ALWAYS prefer Stringable methods over native PHP string functions
- Only use native functions (`strtolower`, `str_starts_with`, `str_contains`) when there is no Stringable alternative
- Pass that object into the DTO. The property type is `Illuminate\Support\Stringable`, not `string`. Do not call `->toString()` in `toDto()` to feed a `string` parameter.
- Call `->toString()` only where the callee's parameter is a scalar `string` and does not accept `Stringable`: `BackedEnum::from()`, `hash()`, `in_array(..., true)`, and `===` / `!==`. Eloquent, the query builder, interpolation, and concatenation accept the object.
- A neighboring DTO that stores `string` is not a reason to keep the conversion.

This ensures type consistency and leverages Laravel's fluent chainable API.

### III. Test-First Development

Feature tests MUST be written before implementation. Use Pest for testing.

**Coverage Requirements:**
- All Actions MUST have corresponding tests
- API endpoints MUST have feature tests (success and error scenarios)
- Tests MUST be independent and runnable in isolation

**Where tests live:**
- HTTP and persistence behavior: `tests/Feature`, one file per endpoint or use case
- Branching inside an Action or Service that one HTTP call cannot reach: `tests/Unit`
- Frontend behavior: `resources/js/**/__tests__`

**What a change must cover:**
- Every new or changed Action has a test that fails if that Action's decision is removed
- Every new or changed API endpoint has a feature test for the success status and for each error status named in the task's spec
- Each acceptance scenario in the task's spec has a passing automated test, or the task records why that scenario cannot be automated

**Mocks:**
- A feature test of an endpoint MUST exercise the real path Controller → Form Request → Action/Service → Resource. Mocking or faking the Action or Service that implements that endpoint does not count as coverage of the contract.
- External I/O MUST be faked: `Http::fake()` or `Http::preventStrayRequests()` for outbound HTTP, `Storage::fake()` for files, `Cache::fake()` for cache, and the matching Laravel fake for mail, queues, and notifications.
- The database used by feature tests is the test database. Do not mock Eloquent to avoid a migration that the feature owns.

Follow `.cursor/skills/pest-testing/SKILL.md` for Pest organization. Where that skill tells you to mock an in-application Action or Service under an endpoint test, this section wins.

### IV. API Standards

**Response Structure:**
- Consistent structure: `data`, `meta`, `errors`
- Embed request ID when available
- Return `snake_case` payloads unless client contract dictates otherwise

**Versioning & Validation:**
- API versioning MUST be implemented for public endpoints
- Request validation MUST use Form Request classes with pipeline-style rules
- Error responses MUST include proper HTTP status codes and structured error messages

**Controllers:**
- Pagination: use Laravel's built-in pagination; pagination logic (query building + `paginate()`) belongs in Actions, not controllers — controllers only pass the paginated result to a Resource
- **ALL controller responses MUST go through a `JsonResource`** — never return raw PHP arrays or bare `response()->json()`. This applies to every response: model serializations, non-model Action results (counts, flags), and delete confirmations (`OkResource`). For **200 OK** responses, return the Resource directly (typed as the Resource class). Use `->response()->setStatusCode(...)` only when a non-200 status is needed (e.g. `HTTP_CREATED`), typed as `JsonResponse`. See `.cursor/skills/http-endpoint-layer/SKILL.md` §Standardized Response Formats.
- Prefer invokable controllers over route closures

Follow `.cursor/skills/http-endpoint-layer/SKILL.md` for HTTP layer implementation and `.cursor/skills/laravel-api-feature/SKILL.md` for end-to-end API feature workflow.

### V. Application Versioning
The application version MUST be stored in `composer.json` and be accessible at runtime via Laravel config.

- Use a single canonical source of truth for the current version.
- Release communication, version exposure, and deployment-facing version workflow MUST remain accurate for customers.
- Follow `.cursor/skills/release-versioning/SKILL.md` for the operational workflow and release artifact details.

### VI. Observability & Documentation
API surface MUST be documented and operationally observable.

- Keep `public/swagger.yaml` current with deployed endpoint behavior.
- Expose health/readiness probes and maintain audit-friendly instrumentation where required.
- Follow `.cursor/skills/observability/SKILL.md` for OpenAPI, probes, tracing, metrics, and audit-trail workflow details.

### VI.a Logging Discipline (CRITICAL)

**Never Log Everything:**
- NEVER add verbose logging to every method, action, or operation
- This creates noise and hides critical issues

**What to Log (ONLY critical system events):**
- Authentication failures
- Payment failures
- External API errors
- Security violations
- Unrecoverable exceptions

**What NOT to Log:**
- Routine operations: successful requests, database queries, cache hits/misses, job processing steps
- **NEVER log sensitive data**: passwords, API keys, tokens, PII, credit card numbers

**Log Levels:**
| Level | Use For |
|-------|---------|
| `Log::emergency()` / `Log::critical()` | System-unusable events requiring immediate action |
| `Log::error()` | Runtime errors needing attention |
| `Log::warning()` | Exceptional occurrences (deprecated API, poor performance) |
| `Log::info()` | Significant events (user registration, payment)—use sparingly |
| `Log::debug()` | Development only, NEVER in production |

**Structured Logging:**
- **PREFER context arrays** over string interpolation:
  ```php
  Log::error('Payment failed', ['user_id' => $id, 'amount' => $amount]);
  ```

**Exception Handling:**
- Let Laravel's exception handler log exceptions automatically
- Don't manually log caught exceptions unless adding critical context
- Use Laravel Telescope for development debugging

### VII. Redis & Caching Optimization (High-Load Systems)

Caching remains a manual, explicit design decision.

- Do NOT use automatic model caching packages.
- NEVER cache Eloquent model objects directly; cache raw attributes and define invalidation strategy explicitly.
- Avoid static property caches across requests.
- Follow `.cursor/skills/caching/SKILL.md` for cache workflow, hydration, invalidation, and Redis-specific guidance.

## Conventions

**Naming:**
- An Action class ends with `Action` and lives in `App\Modules\{Module}\Actions`.
- A Service class ends with `Service` and lives in `App\Modules\{Module}\Services`.
- A Form Request ends with `Request` and lives in `App\Http\Requests`.
- A JsonResource ends with `Resource` and lives in `App\Http\Resources`.
- An internal DTO lives in `App\Modules\{Module}\Data` and contains no business decisions. A DTO that other modules may see lives in `App\Modules\{Module}\PublicApi`.

**Module layout:**
- One business module, one directory: `app/Modules/{Module}/`
- Inside it: `Actions/`, `Services/`, `Models/`, `Repositories/` when a query is shared, internal `Data/`, and `PublicApi/` (the only type other modules may import)
- HTTP delivery stays outside modules: `app/Http/Controllers`, `app/Http/Requests`, `app/Http/Resources`, `app/Http/Middleware`
- Tests for a module live under `tests/Feature/Modules/{Module}` or `tests/Unit/Modules/{Module}`

**Configuration and secrets:**
- `env()` appears only inside files under `config/`.
- Application code reads settings through `config()`.
- A secret (password, token, key, connection string with credentials) MUST NOT appear in a committed file. `.env` stays gitignored. `.env.example` contains empty or obvious placeholders only.

**Errors:**
- Domain failures throw a dedicated exception from `app/Exceptions` or a Laravel HTTP exception at the boundary. Actions do not return `response()`.
- Guard clauses use `throw_if()` or `throw_unless()` at the top of the method.
- Caught exceptions are logged only when the catch adds context that the framework handler does not already have. See Section VI.a.

## Prohibitions

A reviewer rejects the change when any of these are true:

- The diff adds a Composer or npm dependency that the current feature's `plan.md` does not name, together with why the existing dependencies cannot do the job.
- The diff changes a public route URI, HTTP method, request field, response field, or status code that the current task's `spec.md` does not require.
- The diff lowers Larastan below level 8, adds an `ignoreErrors` entry, adds `@phpstan-ignore` without an adjacent comment that names the rule and the condition for removing the ignore, or skips Pint or Larastan in the quality gate.
- Code added or modified by the task contains `TODO`, `FIXME`, `XXX`, or a body whose only behavior is a placeholder (`null`, an empty array, or an exception whose message says the work is not implemented).
- Code outside `App\Modules\{Module}` imports anything from that module except `App\Modules\{Module}\PublicApi`, or queries a table owned by another module, and the current feature's `plan.md` does not record that exact exception (Section I.c).

## Definition of Done

A task is done only when every applicable line below is true:

- For PHP changes: `vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G` exits 0, `vendor/bin/pint --dirty` leaves the diff formatted, and `php artisan test --compact` passes for the tests that cover the task.
- For frontend-only changes under `resources/js/**`: `npm run test:run` and `npx vue-tsc --noEmit` exit 0. A change that also touches PHP runs the PHP gates as well.
- The behavior matches the contract in the task's spec: each acceptance scenario has a passing automated test, or the task records why that scenario cannot be automated.
- No item in Prohibitions is present in the diff.

Follow `.cursor/skills/quality-gates/SKILL.md` for the command order. This section is the acceptance rule; the skill is the procedure.

## Development Workflow

Operational/runbook steps (commands, tool usage, formatting, cache clearing, env handling) live in `.cursorrules`. This constitution stays focused on non-negotiable architecture and quality principles.

### Git Workflow (NON-NEGOTIABLE)

**NEVER commit without explicit human confirmation.** After running quality gates and reviewing changes, ask the user if they want to commit. Follow `.cursor/skills/git/SKILL.md` for the operational commit workflow.

### Agentic Skills (workflow playbooks)

This repo encodes repeatable implementation workflows as Cursor Agentic Skills under `.cursor/skills/`. Skills are the canonical place for task-specific playbooks; this constitution only defines the non-negotiable invariants those skills must follow.

### Subagent Orchestration (Cursor / Dev Spec AI)

The master agent may coordinate subagents, but all subagents MUST follow this constitution and the relevant skills for their scope. Follow `.cursor/skills/subagent-orchestration/SKILL.md` for routing, handoff packets, and coordinator workflow.

### Code Review Requirements

- All PRs MUST verify compliance with this constitution.
- New functionality MUST include appropriate automated tests.
- Required quality gates MUST pass before merge.

### Quality Gates

All changes MUST pass the repo's required quality gates before merge. Follow `.cursor/skills/quality-gates/SKILL.md` for the execution order and `.cursor/skills/phpstan-larastan/SKILL.md` for static-analysis remediation.

### Error Handling
- Use Laravel's exception handling and logging features
    - Create custom exceptions for domain-specific errors
    - Use try-catch blocks for expected exceptions, chaining where possible
    - Prefer `throw_if()` / `throw_unless()` for guard clauses instead of verbose if-throw blocks; place guards at the top of methods.
    - Log only critical errors using structured JSON logs with contextual data (see Section VI.a Logging Discipline)
- Follow `.cursor/skills/laravel-exception-handling/SKILL.md` for operational guidance on exception boundaries, guard style, and HTTP-vs-domain exception choices.

### Middleware & Request Processing
- Implement middleware for request filtering and modification, composing logic inline
    - Use middleware for cross-cutting concerns (authentication, rate limiting, logging)
    - Ensure every request is tagged with a unique request ID header and reflected in responses

### Events & Listeners

- Prefer events over direct coupling for domain events when it improves separation of concerns.
- Follow `.cursor/skills/events/SKILL.md` for implementation workflow and `.cursor/skills/queues/SKILL.md` when listeners should be queued.

### Background Processing

- Long-running or IO-heavy work MUST NOT block controllers.
- Use queues/events for background processing and follow `.cursor/skills/queues/SKILL.md` for retries, chaining, middleware, and after-commit behavior.

## Security Requirements

- Implement proper CSRF protection for web routes
    - Use Laravel Sanctum for API authentication and authorization
    - Validate all user input using Form Requests
    - Sanitize output to prevent XSS attacks
    - Use parameterized queries (Eloquent handles this)
    - Implement rate limiting for API endpoints
    - Enforce HTTPS, secure headers, and transport encryption in all environments
    - Store Sanctum tokens hashed and rotate credentials regularly
- Follow `.cursor/skills/laravel-security/SKILL.md` for auth, authorization, rate limiting, uploads, and secure coding patterns.

## Performance Standards

- Use caching only when justified; follow `.cursor/skills/caching/SKILL.md` for cache workflow, hydration, and invalidation.
- Avoid N+1 queries, use indexes intentionally, and keep long-running work off the request path. Follow `.cursor/skills/model-orm-layer/SKILL.md` for Eloquent models, relationships, casts, query scopes, and ORM performance.
- Treat benchmarking as temporary diagnostic work only; follow `.cursor/skills/benchmarking/SKILL.md`.
- Keep runtime behavior safe for long-lived Octane workers; follow `.cursor/skills/octane-runtime/SKILL.md`.

## Documentation & Observability

Maintain current OpenAPI documentation, health/readiness probes, and audit-friendly observability for deployed behavior. Follow `.cursor/skills/observability/SKILL.md` for operational documentation and instrumentation details. Logging discipline remains defined by Section VI.a.

## Octane & Swoole Requirements

Production runtime MUST remain safe for long-lived Octane workers, and non-local deployments MUST use Swoole unless explicitly changed by architecture decision. Follow `.cursor/skills/octane-runtime/SKILL.md` for worker-safety, configuration, concurrency, and runtime operations guidance.


### Release Notes & Deployment Communication (NON-NEGOTIABLE)

Customer-facing releases MUST include accurate deployment communication, including required env changes, migrations, post-deploy steps, and rollback guidance when applicable. Follow `.cursor/skills/release-versioning/SKILL.md` for the versioning and changelog-fragment workflow.

## Governance

This constitution supersedes all other practices and conventions. All code MUST comply with these principles. Amendments require:
- Documentation of the change
    - Approval from the team
    - Migration plan if breaking changes

All PRs and code reviews MUST verify compliance with this constitution. Complexity must be justified. Use `.cursorrules` for runtime development guidance.

**Version**: 2.1.1 | **Ratified**: 2025-01-27 | **Last Amended**: 2026-09-27
