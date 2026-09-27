---
name: laravel-migration-model-casts
description: Add or modify database schema safely (migration + model updates). Enforces Laravel 12 migration rules, explicit model casts() for all columns, factories/seeders, and targeted Pest tests with Larastan→Pint gates. Use when adding/modifying tables or columns, or when touching Eloquent models.
---

# Laravel Migration + Model Casts Discipline

## Use this skill when

- You are adding or modifying tables, columns, indexes, or schema constraints
- A migration requires matching model updates, factories/seeders, and targeted tests
- You need Laravel 12-safe migration guidance

## Do not use this skill when

- You are only changing query behavior, relations, casts, or eager loading on an existing schema
- You are only changing controllers/requests/resources
- You are only changing Action/Service logic

## Related skills

- Model/ORM-only work: `.cursor/skills/model-orm-layer/SKILL.md`
- End-to-end endpoint work: `.cursor/skills/laravel-api-feature/SKILL.md`
- Finish checks: `.cursor/skills/quality-gates/SKILL.md`

## References

- Canonical non-negotiables: `.specify/memory/constitution.md`
- Quality gates workflow: `.cursor/skills/quality-gates/SKILL.md`

## Workflow

### 1) Identify impact

- Which table(s) change?
- Which model(s) map to those tables?
- Which API resources / DTOs / requests / TS types are impacted?
- Any indexes needed to avoid performance regressions?

### 2) Write tests first (when behavior changes)

- Prefer feature tests when the change impacts API behavior.
- Prefer unit tests when the change is pure domain logic.

### 3) Create/update the migration

- Use Laravel migrations under `database/migrations/**`.
- If modifying an existing column: include **all** prior column attributes (otherwise they get dropped).
- Keep migrations forward-only and idempotent.

### 4) Update the model (casts + fillable + relations)

- Update `app/Models/<Model>.php`:
  - `casts()` MUST include:
    - primary key (`id`)
    - foreign keys
    - domain attributes
    - timestamps (`created_at`, `updated_at`, and `deleted_at` if used)
  - Use appropriate cast types (including Enum classes where applicable).
- If you added/changed a domain attribute that is referenced in resources/DTOs, update them in the same change.

### 5) Update factories/seeders (when tests need them)

- Update or add factories under `database/factories/**` so tests can create valid records.
- Update seeders if the application relies on seeded reference data.

### 6) Run quality gates (order matters)

1. Larastan level 8:

```bash
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
```

2. Pint:

```bash
vendor/bin/pint --dirty
```

3. Targeted tests:

```bash
php artisan test --compact tests/Feature/<RelevantTest>.php
```

## Casting checklist (copy/paste)

- [ ] Model has `protected function casts(): array`
- [ ] Includes **every** column from the table
- [ ] Uses Enums for domain status/type columns
- [ ] Uses `'datetime'` for timestamps, `'date'` for date-only columns
- [ ] Orders casts logically (id → FKs → domain → timestamps)
