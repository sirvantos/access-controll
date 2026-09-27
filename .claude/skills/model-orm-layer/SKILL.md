---
name: model-orm-layer
description: Implement or modify the Eloquent model/ORM layer (models, relationships, casts(), query scopes, eager loading, and performance). Use when adding/changing models, relationships, casts, query behavior, report queries, or fixing N+1/performance issues.
---

# Model / ORM Layer (Eloquent discipline)

## Use this skill when

- You are changing models, relations, casts, scopes, eager loading, or report queries
- You are fixing N+1 issues or query-shape problems without changing schema
- You need Eloquent-specific guidance rather than migration workflow guidance

## Do not use this skill when

- You are primarily changing database schema or writing migrations
- You are only changing controller/request wiring
- You are only changing business orchestration in Actions/Services

## Related skills

- Migration + schema changes: `.claude/skills/laravel-migration-model-casts/SKILL.md`
- Caching: `.claude/skills/caching/SKILL.md`
- Action layer: `.claude/skills/action-layer/SKILL.md`

## References

- Canonical non-negotiables: `.specify/memory/constitution.md`
- Schema changes playbook: `.claude/skills/laravel-migration-model-casts/SKILL.md`

## Eloquent Model Discipline (NON-NEGOTIABLE)

Every Eloquent model MUST define a `casts()` method that explicitly casts **ALL** columns of its associated database table. This serves as self-documentation and ensures type safety.

**Requirements:**
- Include **ALL** columns:
  - Primary key (`id`)
  - Foreign keys
  - Domain fields (strings, integers, booleans, JSON)
  - Timestamps (`created_at`, `updated_at`, `deleted_at`)
- Use appropriate cast types:
  - `'integer'`, `'boolean'`, `'datetime'`
  - `'array'`, `'json'`
  - `'decimal:2'`
  - `'encrypted'`
  - Custom cast classes
  - Enum classes
- Order casts logically:
  1. Primary key
  2. Foreign keys
  3. Domain attributes
  4. Timestamps
- Nullable columns still need casts—Laravel handles null values correctly

**Example:**
```php
protected function casts(): array
{
    return [
        'id' => 'integer',
        'user_id' => 'integer',
        'status' => UserStatus::class,
        'settings' => 'array',
        'is_active' => 'boolean',
        'amount' => 'decimal:2',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

**CRITICAL:**
- **NEVER leave a model without explicit casts**
- Implicit type coercion hides the model's schema and can cause subtle bugs
- When adding migrations that create or modify columns, **ALWAYS** update the corresponding model's `casts()` method

## Database & Data Access

**Query Patterns:**
- Use Eloquent ORM instead of raw SQL when possible, chaining queries directly
- Implement Repository pattern for data access with injected dependencies
- Use query scopes and method chaining for complex queries—no temporary variables
- Implement proper database indexing for performance

**Schema Management:**
- Implement proper migrations and seeders for all schema changes

**Transactions:**
- Use database transactions for data integrity using closures

**Performance:**
- Eager load relationships using chained methods to prevent N+1 queries
- Optimize inverse relations using Chaperone to prevent unnecessary queries
- Ensure proper Chaperone configuration to avoid infinite loops

## Thin Database Transactions (CRITICAL)

Transactions MUST be as short as possible to minimize lock duration and deadlock risk.

**Do:**
- Prepare data and run slow IO (uploads, HTTP calls) **BEFORE** opening the transaction
- Wrap only the necessary DB writes in `DB::transaction(fn () => ...)`
- Use `DB::afterCommit()` for side effects (notifications, events, external calls)
- Favor several small transactions over one large transaction when operations are independent

**Do NOT:**
- Perform HTTP calls, file uploads, queue dispatches, or sleeps inside a transaction
- Hold transactions open while awaiting user actions or external systems

## Goal

Keep models self-documenting, type-safe, and performant:
- explicit `casts()` for all columns
- explicit relationships with return types
- scopes for reusable query constraints
- eager loading to avoid N+1

## Scope (Eloquent layer)

- Models: `app/Models/**`
- Enums: `app/Enums/**`
- Policies/observers (when model behavior requires): `app/Policies/**`, `app/Observers/**`
- Database support: `database/**` (migrations/factories/seeders as needed)

## Workflow

### 1) When changing a model

- Update:
  - `fillable`/guarding if needed
  - relationships (typed)
  - `casts()` to include **all** table columns per Eloquent Model Discipline above
  - Enums for domain statuses/types

### 2) Query behavior

- Prefer query scopes for reusable filters/sorts.
- Local scopes: add a `#[Scope]`-annotated method (Laravel 12) that accepts `Builder $query` and returns `void`/builder; chainable usage (`Model::popular()->active()` per docs). Keep names expressive (e.g., `active`, `forOwner`, `search`).
  **Example:**
```php
    #[Scope]
    public function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
```

- If parameters are needed, place them after the `$query` argument (e.g., `protected function ofType(Builder $query, string $type): void`), then call with `Model::ofType('admin')`
- Avoid global scopes unless absolutely necessary; if used, ensure they don’t hide data incorrectly and are easy to opt out of.
- Eager load relations explicitly (`->with([...])`, `->load([...])`) and counts (`->withCount(...)`).
- When adding report-style endpoints, validate query performance and indexing needs.

### 3) Caching guardrails

- If caching is requested:
  - Never cache Eloquent model objects
  - Cache raw attributes (`getAttributes()`) and hydrate (`newFromBuilder()` / `hydrate()`)

### 4) Tests

- Add/adjust tests for scopes, relationships, casts, and any business rule that depends on them.

