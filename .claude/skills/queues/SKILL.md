---
name: queues
description: Implement or change Laravel queue workflows (jobs, queued listeners, job middleware, retries/backoff, chaining, and after-commit dispatch). Use when adding background jobs, moving slow work off the request path, or changing queue behavior/testing.
---

# Queues (Laravel 12)

Use this skill when you add or change background jobs, queued listeners, job middleware, retries/backoff, job chains, or queue configuration.

## References

- Laravel Queues docs: https://laravel.com/docs/12.x/queues#main-content
- Laravel Events docs (queued listeners): https://laravel.com/docs/12.x/events#queued-event-listeners

## Scope (queue-related code)

- Jobs: `app/Jobs/**`
- Job middleware: `app/Jobs/Middleware/**`
- Queued listeners: `app/Listeners/**`
- Queue config: `config/queue.php`
- Queue migrations: `database/migrations/**` (jobs/failed_jobs/batches)
- Tests: `tests/**`

## When to use queues

- Long-running or IO-heavy work (emails, HTTP calls, file processing).
- Any work that would block an HTTP request or an Octane worker.
- Fan-out work that can run asynchronously.

## Workflow

### 1) Create a job

- Generate: `php artisan make:job ProcessCampaign --no-interaction`
- Jobs live in `app/Jobs/` and implement `ShouldQueue`.
- Keep inputs serializable. Prefer passing resolved Eloquent models over model IDs when the dispatcher already has the model; Laravel serializes queued models by identifier and restores them for the job. Use IDs only when no model instance is available or the job intentionally needs an identifier snapshot. Avoid closures and non-serializable objects.

### 2) Dispatch safely

- Basic: `ProcessCampaign::dispatch($payload)`
- Delayed: `->delay(now()->addMinutes(10))`
- Queue/connection: `->onQueue('high')->onConnection('redis')`
- Transactions: prefer `after_commit` config or per-dispatch `->afterCommit()` to avoid pre-commit execution.

### 3) Retries, backoff, and failure control

- Define attempts: `public int $tries = 3;` or `tries(): int`
- Backoff: `public int|array $backoff = 5;` or `backoff(): int|array`
- Max exceptions: `public int $maxExceptions = 3;`
- Timeout: `public int $timeout = 120;` and optionally `public bool $failOnTimeout = true;`

### 4) Job middleware (when needed)

- Rate limiting: `Illuminate\Queue\Middleware\RateLimited`
- Prevent overlaps: `Illuminate\Queue\Middleware\WithoutOverlapping`
- Throttle exceptions: `Illuminate\Queue\Middleware\ThrottlesExceptions`
- Skip: `Illuminate\Queue\Middleware\Skip`

Attach via:

```php
public function middleware(): array
{
    return [
        new RateLimited('key'),
        (new WithoutOverlapping($this->id))->releaseAfter(60),
    ];
}
```

### 5) Job chaining

- Use `Bus::chain([new FirstJob, new SecondJob])->dispatch();`
- Chain halts on failure; use `->catch()` for failure handling.
- Use `->onConnection()` / `->onQueue()` for consistent routing.

### 6) Queued listeners

- Add `ShouldQueue` to listeners for slow work.
- Use `ShouldQueueAfterCommit` if data is created within a transaction.
- Use `shouldQueue()` to conditionally queue a listener.

### 7) Testing

- `Queue::fake()` for job dispatch assertions.
- `Bus::fake()` for chain assertions (`Bus::assertChained([...])`).
- Prefer targeted tests; avoid real queue processing.
