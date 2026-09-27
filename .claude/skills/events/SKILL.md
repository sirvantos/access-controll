---
name: events
description: Implement or change Laravel events, listeners, queued listeners, and subscribers. Use when introducing domain events, decoupling side effects from Actions/Services, or testing event dispatch behavior.
---

# Events & Listeners (Laravel)

Use this skill when adding or changing domain events, listeners, or event subscribers.

## References
- Laravel Events docs: https://laravel.com/docs/12.x/events

## Scope (event system)

- Events: `app/Events/**`
- Listeners: `app/Listeners/**`
- Subscribers (when used): `app/Listeners/**` (subscriber classes)
- Event registration: `app/Providers/**` or `bootstrap/app.php` (event discovery config)
- Tests: `tests/**` (Event::fake / assertions)

## Create events & listeners
- Generate an event:
  - `php artisan make:event OrderShipped`
- Generate a listener for an event:
  - `php artisan make:listener SendShipmentNotification --event=OrderShipped`
- Event classes live in `app/Events/`, listeners in `app/Listeners/`.
- Keep events as **data containers** (no business logic). Use `Dispatchable` + `SerializesModels`.

## Dispatch events
- Dispatch from domain logic (Action/Service) or controller when appropriate:
  - `OrderShipped::dispatch($order);`
- Conditional dispatch:
  - `OrderShipped::dispatchIf($condition, $order);`
  - `OrderShipped::dispatchUnless($condition, $order);`
- If dispatching inside DB transactions, prefer after-commit:
  - implement `ShouldDispatchAfterCommit` on the event (or use `DB::afterCommit()` to dispatch).

## Queued listeners (when and how)
- Use queued listeners when the work is **slow or IO-bound** (emails, HTTP calls, file processing).
- Implement `ShouldQueue` on the listener:
  - `class SendShipmentNotification implements ShouldQueue`
- Optional runtime queue configuration: `viaConnection()`, `viaQueue()`, `withDelay()`.
- Use `ShouldQueueAfterCommit` when listeners depend on data written in a transaction.
- Add job middleware by defining `middleware()` on the listener (returns array of middleware).
- Define `failed()` to handle failures and set retries with `tries`, `backoff`, `timeout`, `maxExceptions` as needed.

## Event subscribers
- Use subscribers to group multiple event handlers in one class.
- Create a subscriber with a `subscribe(Dispatcher $events): void|array` method and register:
  - `Event::subscribe(UserEventSubscriber::class);` (in a service provider)
- Keep handlers small; delegate heavy work to queued listeners or Jobs.

## Testing
- Use `Event::fake()` to assert dispatches without running listeners:
  - `Event::assertDispatched(OrderShipped::class);`
- If factories rely on events, call `Event::fake()` **after** factory usage.
