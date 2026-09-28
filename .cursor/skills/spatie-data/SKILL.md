---
name: spatie-data
description: Create and configure Spatie Laravel Data DTOs (data objects, casts, defaults, from request). Use when adding/changing DTOs in app/Data, creating data object structures, defining custom casts, or mapping request input to typed DTOs.
---

# Spatie Laravel Data (DTOs)

Use this skill when creating or modifying Data Transfer Objects (DTOs) using Spatie Laravel Data v4.

## Do not use this skill when

- You are only changing Form Request validation rules
- You are only wiring controllers/routes/resources
- You are only changing business rules in Actions/Services without touching DTO structure

## Related skills

- Request validation + DTO mapping: `.cursor/skills/http-request-validation/SKILL.md`
- Action layer consumers: `.cursor/skills/action-layer/SKILL.md`
- End-to-end API work: `.cursor/skills/laravel-api-feature/SKILL.md`

## References

- Spatie Laravel Data v4 docs: https://spatie.be/docs/laravel-data/v4/introduction
- Project DTOs: `app/Data/**`
- HTTP Request validation: `.cursor/skills/http-request-validation/SKILL.md`

## Scope (DTO layer)

- DTOs: `app/Data/**`
- Custom casts (if any): `app/Data/Casts/**`

## When to use DTOs

- Transfer validated input from HTTP layer to Actions/Services.
- Represent structured domain concepts (filters, payloads, results).
- Replace ad-hoc arrays with typed, self-documenting objects.

## Workflow

### 0) Check for reuse before creating a new DTO

Before creating a new class, search `app/Data/<Domain>/` for an existing DTO with the same property set. If one exists, reuse it — rename it to a shape-oriented name if the current name is endpoint-specific.

**Skip the DTO entirely** when the Action's only input is a single model or primitive. Pass the value directly; a DTO wrapping one field adds ceremony with no benefit.

### 1) Create a DTO class

Location: `app/Data/<Domain>/<Name>Data.php`

```php
<?php

declare(strict_types=1);

namespace App\Data\Campaigns;

use Illuminate\Support\Stringable;
use Spatie\LaravelData\Data;

final class CreateCampaignData extends Data
{
    public function __construct(
        public readonly Stringable $name,
        public readonly int $typeId,
    ) {}
}
```

**Rules:**
- ALWAYS use `declare(strict_types=1);`
- ALWAYS mark class `final`
- ALWAYS use constructor property promotion with `readonly`
- ALWAYS use typed properties (never untyped)
- Group by domain: `App\Data\Campaigns\`, `App\Data\Auth\`, etc.
- **ALWAYS use `Stringable` (not `string`) for text properties that originate from Form Request `$this->string()` calls** — this preserves the fluent chainable API and avoids falling back to native PHP string functions in Actions/Services. Use plain `string` only for properties that are never extracted via `$this->string()` (e.g., fixed enum-backed values or raw identifiers).

### 2) Default values

Provide defaults via constructor parameters (PHP 8 syntax):

```php
use Illuminate\Support\Stringable;

public function __construct(
    public readonly Stringable $sort = new Stringable('created_at'),
    public readonly Stringable $direction = new Stringable('desc'),
    public readonly int $page = 1,
    public readonly int $perPage = 20,
) {}
```

### 3) Use Enums (no magic strings)

ALWAYS prefer PHP Enums over string literals:

```php
use App\Enums\CampaignStatus;

public function __construct(
    public readonly CampaignStatus $status,
) {}
```

### 4) Nullable vs optional

- **Nullable (`?Type`)**: property can hold `null` as a valid value
- **Default value**: property is optional at construction time

```php
use Illuminate\Support\Stringable;

public function __construct(
    public readonly ?CampaignStatus $status = null,       // optional + nullable
    public readonly ?Stringable $search = null,            // optional + nullable Stringable
    public readonly int $page = 1,                         // optional, always int
) {}
```

### 5) Array shapes (PHPDoc)

Document array structures with PHPDoc for Larastan:

```php
/**
 * @param array<int, string> $mapping Column index => field name
 * @param array<string> $managerIds
 */
public function __construct(
    public readonly array $mapping,
    public readonly array $managerIds = [],
) {}
```

### 6) Casts (auto-transformation)

Spatie Data casts transform incoming data automatically based on type hints.

**Built-in casts** (auto-applied — no manual conversion needed):
- `string` input → `BackedEnum` property = auto-cast to Enum
- `string` input → `DateTimeInterface`/`Carbon` property = auto-cast to Carbon
- `array` input → nested `Data` class = recursive hydration
- `array` input → `DataCollection` = collection of DTOs

**Custom cast** (only when built-in is insufficient):

```php
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

public function __construct(
    #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
    public readonly Carbon $date,
) {}
```

### 7) From request

In this repo, Form Requests expose an explicit `toDto()` method. Use `Data::from(...)` when the validated payload already matches the DTO shape:

```php
public function toDto(): CreateLeadData
{
    return CreateLeadData::from($this->validated());
}
```

Override the mapping when extra context is needed or when request keys do not match the final DTO exactly:

```php
public function toDto(): UpdateLeadData
{
    return UpdateLeadData::from([
        ...$this->validated(),
        'updated_by' => $this->user(),
    ]);
}
```

**Why this pattern:**
- Keeps request parsing explicit and local to the Form Request
- Spatie's `from()` auto-casts enums, dates, and nested DTOs
- DTO property types drive transformation
- Custom mapping remains easy when route/user context must be injected

**DTO property names MUST match request input keys** (snake_case):

```php
// Request input: { "campaign_id": 5, "status": "active", "comment": "..." }
final class CreateLeadData extends Data
{
    public function __construct(
        public readonly int $campaign_id,        // matches input key
        public readonly LeadStatus $status,      // auto-cast from string
        public readonly Stringable $comment,     // Stringable from $this->string()
    ) {}
}
```

See `.cursor/skills/http-request-validation/SKILL.md` for full Form Request conventions.

### 8) Helper methods on DTOs

Add domain-specific helpers. Prefer `Stringable` methods over native PHP string functions:

```php
public function normalizedEmail(): string
{
    return $this->email->lower()->toString();
}

public function normalizedDirection(): string
{
    return $this->direction->lower()->toString() === 'asc' ? 'asc' : 'desc';
}
```

### 9) Sensitive data

Use `#[SensitiveParameter]` for passwords/tokens:

```php
use SensitiveParameter;

public function __construct(
    public readonly Stringable $email,
    #[SensitiveParameter] public readonly Stringable $password,
) {}
```

## Anti-patterns (avoid)

- **Using `string` for text properties extracted via `$this->string()`**: use `Stringable`. Call `->toString()` only at a scalar boundary (`BackedEnum::from()`, `hash()`, strict `in_array`, `===`). A neighboring `string` property is not a reason to convert in `toDto()`.
- **Creating a new DTO when an existing DTO in the same domain has the same property set**: search first, reuse or rename
- **Wrapping a single model/primitive in a DTO**: pass directly to the Action instead
- **Manual field-by-field mapping when `Data::from($this->validated())` already matches the DTO**: prefer `Data::from(...)` unless extra context or reshaping is required
- **Manual field-by-field mapping**: use `DataClass::from($this->validated())`, not verbose `new Data(field: $this->validated('field'), ...)`
- **Manual enum/date casting**: let Spatie auto-cast via type hints, not `Enum::from()` or `Carbon::parse()`
- **Untyped properties**: always use explicit types
- **Magic strings**: use Enums and typed constants
- **Business logic in DTOs**: keep DTOs as data containers; move logic to Actions/Services
- **Public non-readonly properties**: always use `readonly`
- **Missing `declare(strict_types=1)`**: always include
