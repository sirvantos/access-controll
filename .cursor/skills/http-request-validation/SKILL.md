---
name: http-request-validation
description: Implement incoming request validation in Laravel using dedicated Form Requests with `authorize()`, `rules()`, optional `messages()`, and an explicit `toDto()` method. Use when adding or changing Form Requests, validation rules, nested array validation, file upload validation, DTO mapping, request-context guard checks, or extracting custom validation logic into `app/Rules/*` classes.
---

# HTTP Request Validation (Form Requests + DTO mapping)

## Use this skill when

- You are adding or changing a Laravel `FormRequest`
- You need to validate request payloads, files, or nested arrays
- You need request input mapped into a typed DTO for an Action/Service
- Authorization should live in `authorize()` rather than in the controller

## Do not use this skill when

- You are only wiring routes/controllers/resources without changing validation rules
- You are designing the DTO itself without changing request parsing
- You are changing pure business logic in an Action/Service

## Related skills

- HTTP wiring: `.cursor/skills/http-endpoint-layer/SKILL.md`
- DTOs: `.cursor/skills/spatie-data/SKILL.md`
- End-to-end endpoint work: `.cursor/skills/laravel-api-feature/SKILL.md`
- Exception workflow: `.cursor/skills/laravel-exception-handling/SKILL.md`

## References

- Canonical non-negotiables: `.specify/memory/constitution.md`
- HTTP wiring playbook: `.cursor/skills/http-endpoint-layer/SKILL.md`
- Spatie Data DTOs: `.cursor/skills/spatie-data/SKILL.md`
- Laravel 12 validation docs: https://laravel.com/docs/12.x/validation

## Goal

Validate incoming HTTP input in the HTTP layer and convert it into a typed DTO so Actions/Services stay pure.

## Scope (validation + DTO mapping)

| Layer | Location | Responsibility |
|-------|----------|----------------|
| HTTP wiring | `app/Http/Requests/**` | `authorize()`, compose rules, `messages()`, `prepareForValidation()`, `toDto()` |
| Shared field bundles | `app/Support/Validation/**` (e.g. `ContactRules`) | Static per-attribute rule lists and regex constants reused across requests |
| Domain rule objects | `app/Rules/**` | `ValidationRule` classes (optionally `DataAwareRule` / `ValidatorAwareRule`) for complicated or reusable custom logic |
| DTOs | `app/Data/**` | Typed input for Actions/Services |

## Custom rules: extract early

Laravel docs: closures in `rules()` are for **once-only** logic; **rule objects** (`app/Rules/*`) encapsulate complicated logic.

Compose each attribute's rules in this order — pick the first that fits:

1. Built-in rules + `Illuminate\Validation\Rule` (`enum`, `exists`, `unique`, `requiredIf`, `prohibitedIf`, …).
2. `Rule::exists()->where(...)` / `Rule::unique()->where(...)` for SQL-shaped DB checks.
3. Shared bundles in `app/Support/Validation/**` (e.g. `ContactRules`).
4. `app/Rules/{Domain}/{Name}Rule` implementing `ValidationRule` — for anything reused, querying the DB, encoding domain policy, or exceeding ~5 lines. Use `DataAwareRule` / `ValidatorAwareRule` (or the repo's `ValidatesWithValidator` trait) when needed.
5. Private `*Rule()` method on the Form Request — only when the rule needs `$this->user()` / route model **and** is not reusable (see `UpsertDraftContactRequest::uniqueTaxNumberRule()`).

**Forbidden:** anonymous `function ($attribute, $value, $fail)` inside `rules()` that queries Eloquent, encodes domain policy, is duplicated, or exceeds ~5 lines. Extract to `Rule::exists()->where(...)` or `app/Rules/*` instead.

Custom rule classes use `$fail('validation.key')->translate()` — never hardcoded strings. Prefer `Rule::requiredIf(fn () => …)` over `if`-branched `rules()` arrays. Use `prepareForValidation()` for normalization, not rule closures. Use `after(): array` only for cross-field checks that don't map to a single attribute — return invokable classes when logic is non-trivial.

Canonical example: `app/Rules/Leads/ActiveOrCurrentRejectionReasonRule.php` (Pest-covered).

## Workflow

### 1) Create/update a dedicated Form Request

- Location: `app/Http/Requests/<Domain>/...Request.php`
- Extend `Illuminate\Foundation\Http\FormRequest`
- Implement:
  - `authorize(): bool` using policies/guards (see existing requests using `$this->user()` and `$this->route(...)`)
  - `rules(): array` with explicit rules and correct types

### Localization-first approach (multi-language)

**NEVER hardcode validation messages.** The app uses a multi-language system; all messages must come from translations.

1. **Prefer `attributes` and `custom` in `lang/ru/validation.php`** — if the default messages with translated attributes suffice, no `messages()` override is needed.
2. **For entity-specific messages**, add keys under `validation.products`, `validation.campaigns`, etc. in `lang/ru/validation.php`, then use `__()` in FormRequest:

```php
// lang/ru/validation.php — add entity-specific keys
'products' => [
    'name_required' => 'Поле название обязательно.',
    'name_unique' => 'Продукт с таким названием уже существует.',
],
'attributes' => [
    'name' => 'название',
    'description' => 'описание',
],
```

```php
// FormRequest — use __() for translation keys, never hardcoded strings
public function messages(): array
{
    return [
        'name.required' => __('validation.products.name_required'),
        'name.unique' => __('validation.products.name_unique'),
    ];
}
```

3. **For context-specific overrides** (e.g. CSV contact import vs product form), use nested keys like `validation.custom.contacts.name.required` so the same attribute name can have different messages per context.

### 2) Input is already trimmed — do NOT trim again

Laravel's global `TrimStrings` middleware trims all string input before it reaches Form Requests. By the time `$this->string()` runs, the value is already trimmed. **Never add `->trim()` in `toDto()` or downstream Actions/Services** — it is always redundant.

### 3) Use native Laravel request methods in `toDto()`

Use `$this->string()`, `$this->integer()`, `$this->boolean()`, etc. instead of raw `$this->input()` access for typed extraction.

For optional nullable string fields, use the `nullableString()` helper method:

```php
public function toDto(): ListData
{
    return new ListData(
        string: $this->nullableString('tax_number'),
        integer: $this->nullableInteger('int'),
        array: $this->nullableArray('array'),
        enum: $this->nullableEnum('enum'),
    );
}
```

**Stringable usage in Actions:**

- **Prefer Stringable methods over native PHP string functions** - only use native functions when there's no Stringable alternative:
  ```php
  // Instead of: strtolower($this->email)
  $lowercase = $this->email->lower();
  
  // Instead of: str_starts_with($str, 'prefix')
  $startsWith = $this->string->startsWith('prefix');
  ```

- Stringable implements `__toString()`, so it works in string contexts (interpolation, concatenation) without calling `->toString()`:
  ```php
  $searchTerm = "%{$data->search}%";  // Works via __toString
  ```
- **Use `->toString()` only when PHPStan's type inference fails** - typically for type checks like `in_array()` or strict comparisons (`===`):
  ```php
  // These require ->toString() for PHPStan
  $column = in_array($data->sort->toString(), $sortable, true) ? $data->sort->toString() : 'name';
  $direction = $data->direction->toString() === 'asc' ? 'asc' : 'desc';
  ```

**DTOs must use `Stringable` for text properties extracted via `$this->string()`:**

The DTO receiving these values must type-hint them as `Stringable`, not `string`:

```php
use Illuminate\Support\Stringable;

final class UpdateLeadData extends Data
{
    public function __construct(
        public readonly Stringable $name,          // Stringable from $this->string('name')
        public readonly ?Stringable $comment,      // nullable Stringable
        public readonly LeadStatus $status,        // Enum — not Stringable
        public readonly User $updated_by,
    ) {}
}
```

### 4) Phone validation pattern (E.164 format)

When validating phone numbers, follow the constitution's **NON-NEGOTIABLE** E.164 format requirement:

**Backend validation:**
```php
public function rules(): array
{
    return [
        'phone' => ['nullable', 'string', 'regex:/^\+[0-9]{8,15}$/', 'max:32'],
    ];
}

public function messages(): array
{
    return [
        'phone.regex' => __('validation.phone_e164_format'),
    ];
}
```

**Translation in `lang/ru/validation.php`:**
```php
'phone_e164_format' => 'Введите корректный номер телефона в формате +XXXXXXXXXXX (от 8 до 15 цифр после +).',
```

**Storage guarantee:**
- The regex `^\+[0-9]{8,15}$` enforces the `+` prefix is required
- Example valid: `+79043333333`, `+123456789012345`
- Example invalid: `79043333333` (missing +), `+123` (too short)

### 5) Stringable guidance

- Prefer `Stringable` methods over native PHP string functions when the DTO intentionally stores `Stringable` values
- `Stringable` works in interpolation/concatenation via `__toString()`
- For strict comparisons or APIs like `in_array(..., true)`, call `->toString()` so PHPStan can infer the type correctly

```php
$column = in_array($data->sort->toString(), $sortable, true)
    ? $data->sort->toString()
    : 'name';
```

Do not keep `->toString()` because a neighboring DTO in the same domain stores `string`. New and changed text properties extracted with `$this->string()` are `Stringable`.

### 6) Prefer Enums + typed constants (no magic)

- For domain `status` / `type` inputs, validate using `Rule::enum(MyEnum::class)`
- For limits like file sizes, use typed class constants (e.g. `private const int MAX_FILE_SIZE_KB = 5120;`)

### 7) Validate nested inputs explicitly

For nested arrays (example pattern: `mapping.*.field`):
- validate the parent as `array`
- validate each nested key (`mapping.*.column`, `mapping.*.field`)
- for allowed values, prefer Enum values (e.g. `Rule::in(ContactField::values())`)

### 8) Share Form Requests when shapes match

Before creating a new Form Request, check if an existing one in the same domain has the same `authorize()`, `rules()`, and `toDto()` logic. If so, reuse it instead of duplicating.

**Upsert pattern (shared Store/Update)** is one specific case of this principle.

To prevent code duplication when both store and update endpoints share the same validation rules, use a single **Upsert** Form Request class.

**Key characteristics:**
- Single class handles both create and update operations
- Authorization checks the route parameter to distinguish operations
- Validation rules can conditionally adjust (e.g., ignore unique checks for the current record)

**Example from `app/Http/Requests/DraftContacts/UpsertDraftContactRequest.php`:**

```php
final class UpsertDraftContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $draftContact = $this->route('draftContact');

        // For create operations (no draftContact in route)
        if ($draftContact === null) {
            return $this->user()?->can('create', DraftContact::class) ?? false;
        }

        // For update operations - check if user owns this draft
        return $this->user()?->can('update', $draftContact) ?? false;
    }

    public function rules(): array
    {
        $draftContact = $this->route('draftContact');

        return [
            'tax_number' => [
                'nullable',
                'string',
                $this->uniqueTaxNumberRule($draftContact), // Ignore current record on update
            ],
            // ... other fields
        ];
    }
}
```

Use this pattern when store and update validation logic is nearly identical.

### 9) Guard clauses in `toDto()`

- Follow `.cursor/skills/laravel-exception-handling/SKILL.md` for the canonical guard-clause and exception-selection rules.
- In Form Requests, keep request-context checks explicit in `authorize()` or `toDto()`.
- Use HTTP-layer exceptions here, not domain exceptions that belong in Actions or Services.

### 10) Skip the Form Request when there is no input to validate

When an endpoint has no request body, no query parameters, and authorization is already handled by middleware or a policy, a dedicated Form Request that only wraps `$this->user()` into a single-field DTO is unnecessary ceremony. Instead, resolve the user directly in the controller and pass it to the Action.

## Notes

- Keep request parsing and validation in the Form Request; Actions/Services should receive DTOs or primitives, not the Request object
- If a controller must build a DTO from a raw `Request`, use a dedicated DTO factory such as `YourDto::fromRequest($request)` or `YourDto::from($request->validated())` only when that is already the local pattern
