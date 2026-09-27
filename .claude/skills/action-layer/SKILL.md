---
name: action-layer
description: Implement business logic in the Action/Service layer (pure, no HTTP), invoked by controllers. Use when adding or changing Actions in app/Actions, Services in app/Services, transactions, afterCommit behavior, or orchestration across models and repositories.
---

# Action / Service Layer (business logic)

## Use this skill when

- You are changing pure business logic in `app/Actions/**` or `app/Services/**`
- HTTP contract is already known and the main work is orchestration, transactions, domain rules, or side effects
- You need to split a large workflow into smaller Actions/Services

## Do not use this skill when

- You are only changing routes/controllers/resources
- You are only changing request validation or DTO parsing
- You are mainly changing models, relations, or schema structure

## Related skills

- HTTP wiring: `.claude/skills/http-endpoint-layer/SKILL.md`
- DTOs: `.claude/skills/spatie-data/SKILL.md`
- Events: `.claude/skills/events/SKILL.md`
- Queues: `.claude/skills/queues/SKILL.md`
- Model/ORM work: `.claude/skills/model-orm-layer/SKILL.md`
- Exception workflow: `.claude/skills/laravel-exception-handling/SKILL.md`

## References

- Canonical non-negotiables: `.specify/memory/constitution.md`
- Always-on repo summary: `.cursorrules`
- HTTP wiring playbook: `.claude/skills/http-endpoint-layer/SKILL.md`
- Spatie Data DTOs: `.claude/skills/spatie-data/SKILL.md` (when creating/consuming DTOs)
- Events workflow: `.claude/skills/events/SKILL.md` (when dispatching domain events)
- Queues playbook: `.claude/skills/queues/SKILL.md` (when jobs/queued listeners/chains are involved)

## Goal

Implement business logic as a **pure** Action/Service:
- accepts DTOs/primitives
- returns primitives/data/models
- has no HTTP dependencies (no Request/Response/Session/Cookies)
- has **no authorization/permission checks** — Actions/Services assume the caller is already authorized

## Choose Action vs Service

Use this decision rule before naming or placing a class:

- Prefer an **Action** when the class represents one concrete use case or command with a clear business verb, such as `CreateCampaign`, `UpdateLead`, `ResolveCampaignLeadConflict`, `ListCampaignLeads`, or `BuildLeadContactHistory`.
- Prefer a **Service** when the class exposes a reusable domain capability that is likely to support multiple Actions/resources/features, such as matching, normalization, token generation, calculations, or shared query/lookup helpers.
- Default to an **Action** when a class has one public operation and exists mainly because one endpoint, job, listener, or command needs that operation.
- Default to a **Service** when you expect multiple Actions to depend on the same capability and the class is not itself the business use-case boundary.
- Do NOT use `Service` as a generic bucket for "business logic that is not in a controller." If the class is the named entrypoint for the use case, it should usually be an Action.

Quick heuristics:

- **Action signals**: one main `execute()` method, feature-specific verb in the class name, direct invocation from controller/job/listener/command, owns orchestration or transaction boundary.
- **Service signals**: multiple callers, stable supporting capability, noun-like or capability-oriented name, small API surface with helper methods.

### Actions are not for Resource serialization

- Actions are use-case boundaries invoked from controllers, jobs, commands, listeners, or other Actions.
- Do NOT invoke Actions from `app/Http/Resources/**`.
- Actions return raw domain data (models, primitives, DTOs) — never display-formatted values. Presentation formatting (date strings, display labels, convenience booleans, color codes) belongs in Resources or presentation-oriented Services called from Resources.
- See `.claude/skills/http-endpoint-layer/SKILL.md` §Resource boundary for what Resources own.

Examples from this repo:

- `app/Actions/Leads/UpdateLead.php`: single use case that updates a lead and owns the core workflow.
- `app/Actions/Leads/ListCampaignLeads.php`: single read-side use case for listing leads.
- `app/Services/Contacts/ContactHistoryService.php`: reusable contact-history capability with multiple focused helper methods.

## Scope (business logic only)

- Actions: `app/Actions/**`
- Services: `app/Services/**`
- DTOs (when defined here): `app/Data/**`
- Domain exceptions: `app/Exceptions/**`

## Workflow

### 1) Define inputs/outputs explicitly

- Inputs: **Eloquent models** for entity references (preferred over IDs), **DTOs** for structured multi-field payloads, or **primitives/Enums** for simple scalar values. Never pass bare `int $entityId` when the caller already has the resolved model — pass the model itself.
- **Skip the DTO** when the Action needs only a single model or primitive. Accept it directly (e.g. `execute(User $actor): int`) — wrapping one value in a DTO adds ceremony with no benefit.
- Output: model(s), DTO, or primitive depending on the caller needs.
- Prefer domain Enums over magic strings/numbers.

**Why models over IDs:** Controllers resolve models via route model binding, so the model is already loaded. Passing the model avoids redundant lookup code and gives type safety. This preference also applies to queued Actions/Jobs: pass the resolved Eloquent model when the caller has it, and rely on Laravel queue model serialization. Pass an ID only when the caller genuinely does not have the model instance, or when the job must operate on a historical identifier rather than the model.

### 2) Keep orchestration focused (SRP)

- One Action/Service does one thing.
- If you need sub-steps, extract smaller services or private methods.
- If the extracted class is still the main use-case entrypoint, keep it as an Action.
- If the extracted class is a supporting capability reused by multiple callers, make it a Service.

### 2b) Actions may call other Actions (composition)

- It is OK for an Action to **orchestrate** other Actions (e.g., `CreateCampaignAction` calls `AssignManagersToCampaignAction`).
- Prefer a clear split:
  - **Orchestrator Action**: coordinates a small number of “leaf” actions/services and defines transaction boundaries.
  - **Leaf Action**: does one focused operation and stays easy to test.
- Use dependency injection (constructor or method injection). Avoid static calls.
- Avoid deep chains (A → B → C → D) and circular dependencies; if orchestration becomes complex, introduce a dedicated Service.

### 2c) Upsert pattern (shared Store/Update Action)

To prevent code duplication between store and update operations, use a single **Upsert** Action class that accepts an optional model parameter.

**Key characteristics:**
- Single action handles both create and update
- Accepts `?Model $model = null` as optional second parameter
- Builds attribute array once, then either creates or updates
- Returns the model (created or existing)

**Example from `app/Actions/DraftContacts/UpsertDraftContactAction.php`:**

```php
final class UpsertDraftContactAction
{
    public function execute(UpsertDraftContactData $data, ?DraftContact $draftContact = null): DraftContact
    {
        $attributes = [
            'tax_number' => $data->taxNumber,
            'name' => $data->name,
            'company_name' => $data->companyName,
            // ... all shared fields
        ];

        if ($draftContact === null) {
            // Create new draft
            $attributes['manager_id'] = $data->actor->id;

            /** @var DraftContact $created */
            $created = DraftContact::query()->create($attributes);

            return $created;
        }

        // Update existing draft
        $draftContact->update($attributes);

        return $draftContact;
    }
}
```

Use this pattern when store and update business logic is nearly identical.

### 2d) Queueable Actions (Spatie Queueable Action)

- Use when the same Action should run sync or async. Apply `QueueableAction` trait and call `->onQueue()->execute(...)` to dispatch; omit `onQueue()` for sync.
- Keep input arguments serializable; pass DTOs/primitives/Enums and prefer resolved Eloquent models over model IDs when the caller already has the model. Avoid closures and non-serializable objects.
- Configure queue name if needed: `->onQueue('high')`.
- Chain follow-up actions when needed: `->onQueue()->execute(...)->chain([new ActionJob(AnotherAction::class, $args)])`.
- Add middleware by overriding `middleware()` on the Action (returns array of job middleware).
- Control retries with `public int|array $backoff` or `backoff(): int|array` for per-action retry delays.
- Tests: `Queue::fake()` and use `QueueableActionFake::assertPushed(Action::class)`; avoid touching real queues.

### 3) Data access & pagination

- Prefer Eloquent relationships/scopes and fluent queries.
- Avoid `DB::` unless needed; if used, keep it localized and typed.
- **Pagination belongs in Actions**, not controllers. The Action builds the query and calls `paginate()` / `simplePaginate()` / `cursorPaginate()`. The controller receives the paginated result and wraps it in a Resource.

### 4) Transactions (thin)

- Prepare slow IO and data outside the transaction.
- Wrap only DB writes in `DB::transaction(fn () => ...)`.
- Use `DB::afterCommit()` for side effects (notifications, events, external calls).

If composing Actions:
- Prefer a single transaction owned by the orchestrator (so nested actions don’t each open their own long transaction).

### 5) No authorization in Actions/Services (NON-NEGOTIABLE)

- Actions and Services MUST NOT perform authorization or permission checks (`$user->can()`, `Gate::allows()`, `Gate::authorize()`, policy calls, role checks).
- Authorization belongs exclusively in the HTTP layer: Form Request `authorize()`, controller guards, middleware, or policies.
- Actions receive already-authorized data; they trust that the caller has verified permissions before invocation.
- If an Action needs to know *who* is acting (e.g., to set `manager_id`), accept the user/actor as a DTO property or primitive — but do not check what that user is allowed to do.

### 6) Exceptions in the Action layer

- Follow `.claude/skills/laravel-exception-handling/SKILL.md` for guard-clause style, catch and rethrow rules, and logging discipline.
- In this layer, prefer domain exceptions from `app/Exceptions/**`.
- Do not throw `HttpException` or call `abort()` from Actions or Services.

### 7) Testing expectations

- Add/adjust Pest tests so the Action behavior is covered (unit or feature depending on where it’s exercised).
- Fake external IO (`Http::fake()`, `Storage::fake()`, etc.).

