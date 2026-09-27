---
name: laravel-security
description: Apply Laravel security best practices for this repo, including Sanctum auth, authorization, rate limiting, safe validation, uploads, sensitive data handling, and safe security-related error responses. Use when adding or changing auth endpoints, protected API routes, login or logout flows, file uploads or imports, or when asked to harden security, review OWASP risks, or perform a security review.
---

# Laravel Security (repo baseline)

## Scope (security-relevant code)

- Policies/gates: `app/Policies/**`, `app/Providers/**`
- Middleware: `app/Http/Middleware/**`
- Requests/validation: `app/Http/Requests/**`, `app/Rules/**`
- Auth controllers/endpoints: `app/Http/Controllers/Api/V1/**`

## References

- Canonical policy: `.specify/memory/constitution.md`
- Always-on repo summary: `.cursorrules`
- HTTP validation workflow: `.claude/skills/http-request-validation/SKILL.md`
- Endpoint wiring workflow: `.claude/skills/http-endpoint-layer/SKILL.md`
- Action purity workflow: `.claude/skills/action-layer/SKILL.md`
- Exception workflow: `.claude/skills/laravel-exception-handling/SKILL.md`

## Current auth model (do not “fix” unless the task is about auth)

- APIs are protected via `auth:sanctum` middleware.
- Login issues a **Sanctum personal access token** (Bearer) and the SPA stores it for subsequent requests.

If a task explicitly asks to harden SPA auth, propose migrating away from localStorage tokens to httpOnly cookies + CSRF flow, but do not change auth strategy as a side-effect of unrelated work.

## Security checklist (use during implementation)

### 1) Authentication (AuthN)

- Protected endpoints MUST be behind `auth:sanctum` unless explicitly public.
- Never log credentials or tokens.
- Token issuance:
  - name tokens meaningfully (current pattern uses `'ldap-auth-token'`)
  - consider token abilities only if there is a clear requirement for scoped access

### 2) Authorization (AuthZ)

- Use policies/gates or Form Request `authorize()` checks (preferred).
- Do not “role-check” inside Actions for request-level permissions; keep those checks in HTTP layer/policies.
- If an endpoint is role-restricted, enforce it with:
  - policy checks (`$user->can(...)`) and/or
  - the existing `role` middleware alias when appropriate.

### 3) Rate limiting / abuse protection

- Add throttling to endpoints that are brute-forceable or expensive (login, search, imports, external integrations).
- When adding a rate limiter key, ensure the identifier matches the actual payload:
  - this repo’s login request uses `username` (not `email`), so rate-limit keys must align.

### 4) Input validation + canonicalization

- Validate using Form Requests and map to DTOs (`toDto()`).
- Prefer Enums and typed constants over magic strings/numbers.
- Be explicit about nested arrays (`mapping.*`) and files (type + size).
- Do not trust client-provided IDs: validate existence and authorize access.

### 5) File uploads / imports

- Validate:
  - file type (`mimes` / `mimetypes`)
  - file size (typed constant)
  - expected structure (e.g., mapping array)
- Store files via Laravel storage; avoid predictable filenames.
- Never process untrusted files inside long DB transactions.

### 6) Data exposure (responses)

- Return API Resources; never return raw models.
- Do not expose secrets or internal fields (tokens only appear in login responses where required).
- Avoid leaking exception messages or stack traces in API responses.
- Follow `.claude/skills/laravel-exception-handling/SKILL.md` when deciding whether to bubble, wrap, or translate a security-relevant exception.

### 7) Logging discipline (security-sensitive)

- Log only critical security events (auth failures, external API failures, security violations).
- Never log sensitive data (passwords, tokens, PII).
- Prefer structured logs with minimal identifiers (e.g. user id, request id) and avoid emails/phone numbers unless explicitly required.

### 8) Tests (security behavior)

- Add tests for:
  - unauthenticated access (401)
  - unauthorized access (403)
  - rate limiting behavior (429) when implemented
  - validation failures (422)
- Prevent real external calls in tests (`Http::preventStrayRequests()` / `Http::fake()`).
