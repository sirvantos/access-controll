---
name: spatie-queueable-action
description: Queue Laravel Actions with spatie/laravel-queueable-action. Use when an app/Actions class should run synchronously or asynchronously, when adding QueueableAction, ActionJob chains, queue middleware, retries/backoff, Horizon tags, or QueueableActionFake assertions.
---

# Spatie Queueable Action

Use this skill when an `app/Actions/**` class should be executable both inline and on Laravel's queue through `spatie/laravel-queueable-action`.

## Related skills

- Business logic boundaries: `.cursor/skills/action-layer/SKILL.md`
- Laravel queues: `.cursor/skills/queues/SKILL.md`
- Pest assertions: `.cursor/skills/pest-testing/SKILL.md`
- DTO inputs: `.cursor/skills/spatie-data/SKILL.md`

## When to use

- The work is a named business Action and may be slow, IO-heavy, or triggered after a request returns.
- The same use case needs both sync execution (`execute(...)`) and async dispatch (`onQueue(...)->execute(...)`).
- Constructor dependencies should come from the container instead of being serialized into a Job.

Prefer a normal Laravel Job when the class is purely infrastructure work and does not represent a domain use case. Prefer queued listeners when the trigger is an event and the listener is the async boundary.

## Action shape

```php
use Spatie\QueueableAction\QueueableAction;

final class DistributeCampaignLeadsAction
{
    use QueueableAction;

    public int $tries = 1;

    public function __construct(
        private readonly SomeService $service,
    ) {}

    public function execute(SomeData $data): void
    {
        // Business logic only.
    }
}
```

Rules:
- Keep the Action pure: no HTTP `Request`, response, session, cookie, or authorization logic.
- Do not mark the Action class `readonly` if queue configuration or middleware needs mutable per-dispatch state.
- Use constructor injection for services/actions from the container.
- Keep `execute()` arguments serializable: DTOs, primitives, Enums, and resolved Eloquent models.
- Prefer Eloquent models over model IDs when the caller already has the model; Laravel queue serialization stores the model identifier and restores the model when the job runs.
- Use IDs only when no model instance is available or the Action intentionally needs an identifier snapshot instead of a restored model.

## Dispatch pattern

```php
$action->execute($data);

$action
    ->onQueue((string) config('lead-assignment.queue'))
    ->execute($data);
```

Use a small preflight/orchestrator Action when you need to decide whether queueing should happen before dispatching. Return a typed result DTO instead of raw arrays.

## Middleware, retries, and tags

Define queue controls on the Action:

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;

public int $tries = 3;

/**
 * @return array<int, object>
 */
public function middleware(): array
{
    return [
        (new WithoutOverlapping($this->overlapKey))->releaseAfter(60),
    ];
}

/**
 * @return array<int, int>
 */
public function backoff(): array
{
    return [5, 30, 120];
}

/**
 * @return array<int, string>
 */
public function tags(): array
{
    return ['campaign:'.$this->campaignId];
}
```

Use typed public properties for retry controls (`tries`, `timeout`, `maxExceptions`, `backoff`) and typed methods for dynamic values.

## Chaining actions

Use `ActionJob` when chaining queueable actions:

```php
use Spatie\QueueableAction\ActionJob;

$args = [$userId, $data];

$action
    ->onQueue('high')
    ->execute(...$args)
    ->chain([
        new ActionJob(AnotherAction::class, $args),
    ]);
```

If the chain grows complex, switch to the general queue skill and consider `Bus::chain()` with dedicated Jobs.

## Testing

Always fake the queue before asserting queueable Actions:

```php
use Illuminate\Support\Facades\Queue;
use Spatie\QueueableAction\Testing\QueueableActionFake;

Queue::fake();

$action->onQueue('default')->execute($data);

QueueableActionFake::assertPushed(MyAction::class);
```

Useful assertions:
- `QueueableActionFake::assertPushed(MyAction::class)`
- `QueueableActionFake::assertPushedTimes(MyAction::class, 2)`
- `QueueableActionFake::assertNotPushed(MyAction::class)`
- `QueueableActionFake::assertPushedWithChain(MyAction::class, [...])`
- `QueueableActionFake::assertPushedWithoutChain(MyAction::class)`

Run targeted Pest tests for the caller and the Action behavior. For backend changes, finish with the repo quality gates in `.cursor/skills/quality-gates/SKILL.md`.
