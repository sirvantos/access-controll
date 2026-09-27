---
name: phpstan-larastan
description: Run and fix Larastan (PHPStan) level 8 for this Laravel project. Use when static analysis fails, when type-related changes are made, or before finishing backend work that should pass strict analysis.
---

# Larastan (PHPStan) level 8

Use this skill whenever static analysis is required or before finishing backend work.

## Command (project default)

```bash
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
```

- Level: 8 (strict) per `phpstan.neon`.
- Run from repo root.

## Common fixes

- **Missing types**: add return/param types; prefer enums/typed constants over magic strings.
- **Models**: ensure `casts()` covers all columns; add PHPDoc for relations when Larastan needs context.
- **DTOs/Value Objects**: add explicit types and constructors with property promotion.
- **Null handling**: guard nullable properties with early returns, `throw_if/throw_unless`, or an explicit `if (...) { throw ... }` when the analyzer reads it more clearly.
- **Exception flow**: see `.claude/skills/laravel-exception-handling/SKILL.md` when the fix depends on choosing between guard helpers, direct throws, or safer exception boundaries.
- **Unused code**: remove dead variables/imports.
- **Factories**: ensure attributes align with casts/enums.
- **Static calls**: prefer injected services where possible; suppress only as a last resort.

## When to rerun

- After type changes, signature updates, new models/DTOs, or relation tweaks.
- Before final handoff or PR.

## Do not

- Do not downgrade the level or skip errors.
- Avoid `@phpstan-ignore-next-line` unless absolutely necessary; prefer fixing the root cause.
