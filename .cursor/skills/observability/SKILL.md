---
name: observability
description: Implement or update observability and operational documentation for this repo. Use when changing OpenAPI docs, health/readiness probes, queue metrics, tracing around external calls, or audit trails for security-sensitive actions.
---

# Observability & Documentation

## Use this skill when

- You are updating `public/swagger.yaml` or public API documentation
- You are adding or changing `/health` or `/ready` infrastructure probes
- You are instrumenting queue metrics, job durations, failure counts, or tracing
- You are adding audit trails for security-sensitive actions

## Do not use this skill when

- You are only changing endpoint business logic with no documentation or observability impact
- You are only changing logging verbosity rules; follow the constitution logging discipline
- You are only changing release notes or version metadata

## Related skills

- API endpoints: `.cursor/skills/laravel-api-feature/SKILL.md`
- Security-sensitive actions: `.cursor/skills/laravel-security/SKILL.md`
- Events/audit hooks: `.cursor/skills/events/SKILL.md`
- Queued jobs and listeners: `.cursor/skills/queues/SKILL.md`

## Goal

Keep deployed APIs observable, documented, and operationally supportable without mixing runtime logging policy into feature code.

## Scope

- OpenAPI: `public/swagger.yaml`
- Infra probes / routes: `routes/**`, `app/**`
- Queue instrumentation / tracing hooks: `app/**`, `config/**`
- Customer/developer docs when explicitly requested: `docs/**`

## Workflow

### 1) Keep the API contract documented

- Update `public/swagger.yaml` for every deployed endpoint shape change
- Include auth, request schema, response schema, and relevant error responses
- Keep examples aligned with real API Resources and frontend expectations

### 2) Expose health and readiness probes

- `/health` should confirm the app is reachable
- `/ready` should reflect whether required dependencies are ready for traffic
- Keep probe behavior fast, deterministic, and infrastructure-friendly

### 3) Instrument async and external boundaries

- Publish queue metrics, job durations, and failure counts where the app already records them
- Add tracing spans around external service calls when supported by the stack
- Prefer centralized instrumentation points over scattering ad-hoc timing code

### 4) Capture audit trails where required

- Security-sensitive actions should produce audit-friendly records
- Use events or dedicated logging/audit channels instead of burying audit logic inside controllers
- Record the minimum useful identifiers; avoid sensitive payload leakage

## Notes

- Follow the constitution for logging policy: log only critical events, never sensitive data
- Use `.cursor/skills/benchmarking/SKILL.md` for temporary performance investigation, not observability
