<!--
Sync Impact Report
==================
Version change: 1.5.5 → 1.5.6
Modified principles:
  - Added "Reuse Before Create (DRY — NON-NEGOTIABLE)" to Section I.a
Added sections:
  - None
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
- Accept Eloquent models (for entity references), DTOs (for structured payloads), or primitives/Enums as input — prefer passing the resolved model over a bare ID
- Return data/primitives
- No HTTP-specific logic (Request, Response, Session, Cookies)
- No authorization/permission checks (`$user->can()`, `Gate::allows()`, policy calls, role checks) — authorization belongs in Form Request `authorize()`, controller guards, middleware, or policies

Controllers handle HTTP concerns exclusively:
- JsonResource, redirects, status codes
- Use dependency injection and service containers

### I.a OOP & Design Patterns (SOLID - NON-NEGOTIABLE)

**Single Responsibility**: Each class MUST have one reason to change. Actions do one thing; Services encapsulate one domain concern.
**Action vs Service selection**:
- Prefer an **Action** when the class is the named entrypoint for a single use case or command (create, update, resolve, list, build, sync, assign), especially when it owns orchestration, transaction boundaries, or is called directly from a controller, job, command, or listener.
- Prefer a **Service** when the class provides a reusable domain capability that supports multiple use cases, such as matching, normalization, token generation, calculations, or shared query-building/lookup rules.
- Do NOT use `Service` as the default bucket for endpoint-specific business logic. If a class has one public operation and exists primarily to fulfill one feature/use case, default to an Action unless it is intentionally shared as a reusable capability.
- Actions MAY depend on Services; Services SHOULD NOT become mini-controllers or carry HTTP/presentation concerns.
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

This ensures type consistency and leverages Laravel's fluent chainable API.

### III. Test-First Development

Feature tests MUST be written before implementation. Use Pest for testing.

**Coverage Requirements:**
- All Actions MUST have corresponding tests
- API endpoints MUST have feature tests (success and error scenarios)
- Tests MUST be independent and runnable in isolation

**Mocking (REQUIRED):**
- Tests MUST use mocks to isolate units and prevent side effects
- **Every external HTTP request MUST be mocked** using `Http::fake()` or `Http::preventStrayRequests()`
- Use `Storage::fake()` for file operations
- Use `Cache::fake()` for caching
- Use other Laravel fakes to avoid external dependencies

Follow `.cursor/skills/pest-testing/SKILL.md` for Pest testing patterns, mocking, and test organization.

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

**Version**: 1.5.6 | **Ratified**: 2025-01-27 | **Last Amended**: 2026-03-26
