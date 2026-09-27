---
name: quality-gates-makefile
description: End every substantive code change with the repository Makefile quality gate. Use when finishing a task that touched application source, Pest tests, frontend source, CI/build-affecting config, or the Makefile.
---

# Final Validation: `make quality-gates`

Before you finish a task that touched application source (PHP, Pest, `resources/js/**`, config that affects CI/build, `Makefile`), run from the repository root:

```bash
make quality-gates
```

This first runs `deps-sync` (`composer install --no-interaction` and `npm install`) so `vendor/` and `node_modules/` match the lockfiles, then Larastan, Pint (`--test`), the full Pest suite (test DB via Docker), then frontend verification (ESLint, Vitest, `vue-tsc`).

- Do not run `make quality-gates` for documentation-only changes, including Speckit-only artifacts under `specs/**` such as `spec.md`, `plan.md`, `tasks.md`, `research.md`, `data-model.md`, `quickstart.md`, `constraints.md`, and planning contracts. For those changes, validate the document format and content instead.
- If it fails, fix the failures and re-run until the command exits successfully.
- Do not tell the user the work is complete or merge-ready while `make quality-gates` is red or was not run for this change batch.
- If the user explicitly asks to skip full gates (time constraints, partial work), say so in the reply and run the narrowest checks you still can (for example, targeted tests only).

## Vue Handlers

Do not pass `reload` or similar functions directly to `@click` when the function has custom typed arguments; Vue injects the `PointerEvent`. Use `() => void reload()` or an equivalent wrapper so the handler signature matches.

This skill aligns with `.cursor/skills/quality-gates/SKILL.md` for ordering and scope; it mandates the Makefile entry point as the default final gate.
