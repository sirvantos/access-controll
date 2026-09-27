---
name: subagent-orchestration
description: Coordinate work across backend/frontend/contract subagents (Claude Code agents under .claude/agents/, ported from the same Cursor/Dev Spec AI workflow). Use when implementing features that span Laravel + Vue, when running work in parallel, or when the coordinator must route tasks to the right subagent and enforce the correct Agentic Skills.
---

# Subagent Orchestration (Coordinator Playbook)

## References

- Canonical policy: `.specify/memory/constitution.md` (Subagent Orchestration section)
- Skills catalog: `.claude/skills/*`

## Goal

Split work cleanly across subagents while keeping a single contract, consistent response shapes, and predictable quality gates.

## Default subagents

| Subagent | Model (Claude Code) | Rationale |
|----------|----------------------|-----------|
| **Backend** (`.claude/agents/backend.md`) | `sonnet` | Multi-step + failure-prone (tests, Larastan, migrations, policies) |
| **Frontend** (`.claude/agents/frontend.md`) | `sonnet` | UI/API integration and TS typing benefit from reasoning depth |
| **Contract** (`.claude/agents/contract.md`) | `haiku`, `tools` restricted to `Read, Grep, Glob, Edit, Write` | Mechanical YAML edits; cheaper model sufficient when contract is specified, no `Bash` so it can't run arbitrary commands |

**Model tier strategy**: Use expensive models for high-risk/ambiguous work (auth, transactions, complex logic); use cheaper models for narrow/mechanical tasks (OpenAPI edits, formatting, type generation). Set the tier per subagent via the `model:` field in its `.claude/agents/*.md` frontmatter (`opus` | `sonnet` | `haiku` | a specific model id | `inherit`).

## Optional reviewer subagents

| Subagent | When to spawn | Model (Claude Code) |
|----------|---------------|----------------------|
| **Security** (`.claude/agents/security-reviewer.md`) | auth/permissions/uploads/rate limiting changes | `opus`, `tools` restricted to `Read, Grep, Glob, Bash` (no `Edit`/`Write` — enforces read-only review) |

Spawn optional reviewers explicitly when the feature touches their domain. Claude Code has no separate `readonly` flag for subagents — read-only scope is enforced by omitting `Edit`/`Write` from the agent's `tools:` allow-list in its frontmatter.

## Coordinator workflow

### 1) Produce the “handoff packet” (required)

Every subagent gets the same packet header:

- **Feature goal** (1–2 sentences)
- **Contract**:
  - endpoint(s): method + `/api/v1/...`
  - auth: public / `auth:sanctum` / role/policy
  - request keys (snake_case)
  - response envelope + key fields
  - error cases (401/403/404/422/429 as applicable)
- **Constraints**: constitution non-negotiables, performance/security constraints if any
- **Files / directories** to touch
- **Skills to follow** (explicit list; do not rely on auto-selection)
- **Deliverable**: what “done” looks like + required tests/docs

### 2) Spawn subagents in parallel

Use parallelism when subagents touch different areas (backend vs frontend vs swagger).

### 3) Reconcile outputs

Coordinator verifies:
- backend resource shape matches OpenAPI and frontend TS types
- auth/permissions are enforced consistently (routes + request authorize + policies/middleware)
- tests exist and cover key paths

## Subagent scopes + required skills

### Backend subagent (Laravel)

- Scope (common root only — see skill-specific scopes for details):
  - `routes/**`
  - `app/**`
  - `database/**`
  - `tests/**`
- Must follow:
  - `caching`
  - `http-endpoint-layer`
  - `http-request-validation`
  - `spatie-data` (when creating/modifying DTOs in app/Data)
  - `action-layer`
  - `model-orm-layer`
  - `laravel-security` (when auth/permissions/uploads/imports)
  - `events` (when domain events/listeners/subscribers are added/changed)
  - `queues` (when jobs/queued listeners/chains/middleware are added/changed)
  - `octane-runtime` (when touching long-lived worker safety, static state, concurrency, or Octane config)
  - `benchmarking` (when profiling bottlenecks or adding/removing performance measurement code)
  - `phpstan-larastan` (static analysis)
  - `pest-testing` (write/run feature + action tests)
  - `quality-gates` (at finish)

### Frontend subagent (Vue)

- Scope: `resources/js/api/**`, `resources/js/pages/**`, `resources/js/components/**`, `resources/js/stores/**`, `resources/js/router/**`, `resources/js/types/**`
- Must follow:
  - `vue-api-integration`
  - `laravel-security` (when auth/permissions/uploads are involved)

### Contract subagent (OpenAPI + alignment)

- Scope: `public/swagger.yaml` (+ sanity-check backend resources + frontend TS shapes)
- Must follow:
  - `laravel-api-feature` (envelope/auth/errors conventions)

## Required output format (all subagents)

- **Touched files**: list paths
- **Summary**: 3–6 bullets of what changed
- **Contract notes**: any deviations or decisions
- **Risks/edge cases**: 2–5 bullets
- **Test plan**: what to run / what scenarios covered

