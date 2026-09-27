---
name: quality-gates
description: Run the project's required quality gates in the correct order (Larastan level 8 → Pint → targeted Pest tests). Use when finishing a change, preparing a PR, or when asked to "run checks", "run phpstan/larastan", "format", or "run tests".
---

# Quality Gates (required order)

This skill is the default for backend / Laravel work. For frontend-only changes under `resources/js/**`, use the frontend verification flow below instead of running Larastan and Pint by default.

## Frontend-only verification

Run these when the change is limited to the Vue SPA / Vite frontend:

```bash
npm run test:run
npx vue-tsc --noEmit
```

Then check IDE diagnostics / `ReadLints`.

Also run a production build when the change affects frontend tooling or boot/config paths such as:

- `package.json`
- `vite.config.ts`
- `resources/js/app.ts`
- `resources/js/router/**`
- frontend bootstrap / entry wiring

```bash
npm run build
```

## 1) Larastan (PHPStan) level 8

See `.cursor/skills/phpstan-larastan/SKILL.md` for details and common fixes.

```bash
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
```

## 2) Pint (formatting)

```bash
vendor/bin/pint --dirty
```

## 3) Targeted tests (Pest)

Run the minimum set of tests that cover the change:

```bash
php artisan test --compact tests/Feature/<RelevantTest>.php
```

Or filter by test name when appropriate:

```bash
php artisan test --compact --filter=<TestName>
```

## Notes / common failures

- If you get a Vite manifest error (`Unable to locate file in Vite manifest`), the fix is usually `npm run build` or running the dev server (`npm run dev` / `composer run dev`) depending on how the project is started.
