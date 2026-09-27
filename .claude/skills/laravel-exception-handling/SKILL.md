---
name: laravel-exception-handling
description: Handle Laravel exceptions consistently across actions, requests, controllers, and API responses. Use when adding or changing custom exceptions, deciding between domain exceptions and HttpException, writing guard clauses with throw_if or throw_unless, using abort, adding try-catch blocks, shaping error responses, or reviewing exception logging and leakage.
---

# Laravel Exception Handling

## Use this skill when

- You are adding or changing custom exceptions in `app/Exceptions/**`
- You need to decide between a domain exception, `HttpException`, `abort()`, or a framework exception
- You are writing guard clauses with `throw_if()` / `throw_unless()`
- You are adding `try-catch` blocks, wrapping exceptions, or rethrowing
- You are reviewing API error leakage, exception logging, or tests for failure paths

## Do not use this skill when

- You are only changing validation rules with no exception behavior change
- You are only formatting or renaming existing exceptions
- The task is purely about queue retry configuration (`$tries`, `$backoff`, `$maxExceptions`) without changing exception semantics

## Related skills

- Action layer: `.claude/skills/action-layer/SKILL.md`
- HTTP endpoint layer: `.claude/skills/http-endpoint-layer/SKILL.md`
- HTTP request validation: `.claude/skills/http-request-validation/SKILL.md`
- Laravel security: `.claude/skills/laravel-security/SKILL.md`
- Larastan remediation: `.claude/skills/phpstan-larastan/SKILL.md`

## References

- Canonical invariants: `.specify/memory/constitution.md`
- Always-on repo summary: `.cursorrules`

## Goal

Choose the right exception type for the layer, keep failure handling explicit, and avoid leaking internal details to clients or logs.

## Decision model

### 1) Pick the exception for the layer

- Domain rule failed inside an Action or Service:
  - throw a custom domain exception from `app/Exceptions/**`
  - do not throw `HttpException` or call `abort()` in the Action layer
- HTTP auth, authorization, or request-context failure in controllers or Form Requests:
  - use `HttpException`, authentication exceptions, model not found behavior, or framework-native HTTP exceptions
- Validation failure for request payloads:
  - let Laravel validation fail through `FormRequest` / validation exceptions
- External dependency or infrastructure failure:
  - prefer bubbling the original exception unless the boundary needs a repo-specific abstraction or safer message

### 2) Keep boundary ownership clear

- Actions and Services own domain failures
- Form Requests and controllers own HTTP-specific failures
- Controllers translate successful results to Resources and let Laravel/global handlers shape error responses
- Do not move request-specific exception behavior into pure business logic

## Guard clauses

Prefer `throw_if()` / `throw_unless()` for simple top-of-method guards:

```php
throw_unless($user instanceof User, new AuthenticationException);
throw_if($user->isBlocked(), HttpException::class, ['code' => 403]);
throw_if($amount <= 0, InvalidArgumentException::class, 'Amount must be positive');
```

Place guards before the main logic, immediately after loading the needed user, route model, or dependency result.

Laravel's `throw_if()` / `throw_unless()` helpers also accept a `Closure` as the exception argument, so complex exception construction can still stay in helper form when it remains readable:

```php
throw_if($shouldFail, fn () => $this->buildException());
```

Prefer the closure form only when the callback clearly returns the exception instance. If the helper form becomes harder to read or works poorly with static analysis, use a normal `if (...) { throw ... }` branch instead.

## `abort()` vs `HttpException` vs custom exceptions

- Prefer custom exceptions for domain failures that can happen outside HTTP
- Prefer `HttpException` or framework-native HTTP exceptions in controllers and Form Requests when the failure is inherently request/response scoped
- Prefer `abort()` only for small HTTP-layer exits when it is already the local pattern and does not hide important intent
- Do not use `abort()` in Actions or Services

## Catching and rethrowing

- Do not add `try-catch` unless it changes behavior
- Good reasons to catch:
  - wrap a low-level exception in a domain-specific exception
  - add safe contextual behavior before rethrowing
  - translate an infrastructure failure at a boundary
- Bad reasons to catch:
  - logging and then immediately rethrowing the same exception
  - swallowing the exception and returning partial success
  - converting everything into a generic `Exception`

When wrapping, preserve the original exception as the previous throwable when possible.

## Logging discipline

- Let Laravel's exception handler log exceptions by default
- Log caught exceptions only when you are adding critical context the global handler does not have
- Never log passwords, tokens, LDAP credentials, or other sensitive payloads
- Prefer structured context arrays over interpolated strings

## API response safety

- Do not expose stack traces or internal exception messages in API responses
- Return stable, intentional error structures and status codes
- Keep domain exception messages customer-safe if they may surface through the API
- If a low-level exception is not safe for clients, wrap or translate it before it reaches the response layer

## Layer-specific rules

### Actions and Services

- Throw domain exceptions, not HTTP exceptions
- Guard early and keep transaction boundaries thin
- Do not catch exceptions only to convert them into HTTP status codes

### Form Requests

- Keep authorization and route-context checks in `authorize()` or `toDto()`
- Use HTTP-layer exceptions for request-context failures
- Keep DTO mapping explicit and typed

### Controllers

- Use concise HTTP-layer guards after reading the authenticated user or route resource
- Delegate business failures to the Action layer
- Return Resources; do not manually serialize exception internals

## Larastan / PHPStan notes

- Stronger analysis may flag guards as always true or always false when PHPDoc or casts already narrow the type
- Fix the type source first when possible
- If helper-based guards confuse the analyzer, prefer an explicit `if (...) { throw ... }`
- Do not weaken static analysis or add ignores before checking whether the exception flow itself can be made clearer

## Testing expectations

- Add or update tests for the failure path that matters:
  - domain exception thrown
  - 401 / 403 / 404 / 422 / 429 response as applicable
  - safe error payload shape
- Fake external IO when exception behavior depends on external services
- Assert the intended failure behavior, not implementation trivia
