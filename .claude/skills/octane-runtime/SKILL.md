---
name: octane-runtime
description: Laravel Octane with Swoole runtime discipline for long-lived workers. Use when writing code for Octane environments, ensuring request-agnostic code, handling static state, or configuring workers.
---

# Octane Runtime Discipline (CRITICAL)

Laravel Octane with Swoole runs long-lived workers; all code MUST remain request-agnostic.

## NEVER

- Store request-specific data in static properties, singletons, or class-level variables
- Cache Request, Response, Session, or Cookie objects in properties/static variables (will leak between requests)
- Use global variables or superglobals (`$_GET`, `$_POST`, `$_SESSION`) directly
- Store database connections or query results in static properties
- Cache data between requests in static properties (use Cache/Redis instead)
- Rely on class-level static counters or state

## ALWAYS

- Use dependency injection for services (never store in static properties)
- Ensure closures and callbacks are stateless
- Use `Octane::isolated()` for code that might mutate global state
- Close database connections, file handles, external resources in `finally` blocks
- Use coroutine-safe clients for concurrent operations
- Wrap non-coroutine-safe code in `Octane::concurrently()` or use queues
- Flush mutated singletons using lifecycle hooks (`Octane::reset`, `Octane::flush`, `Octane::tick`)

## AVOID

- Blocking operations (`sleep`, `usleep`, remote `file_get_contents`) in request handlers
- FPM-specific helpers or assumptions about request lifecycle
- Storing user authentication state in static properties

## PREFER

- Fresh instances over cached singletons for request-scoped operations
- Dependency injection over static facades when state might persist
- Queued jobs for long-running or blocking operations

## VERIFY

All third-party packages are Swoole-compatible. If they cache state statically, wrap usage in `Octane::isolated()` or find alternatives.

## Configuration

- Configure worker memory limits and restart thresholds
- Cap `octane.max_requests` / `octane.max_execution_time` to force periodic restarts
- Monitor memory growth; automate reloads through `octane:reload`
- Leave Octane's lifecycle listeners enabled:
  - `FlushTemporaryContainerInstances`
  - `DispatchQueuedClosure`
  - `DisconnectFromDatabases`
  - Redis disconnect
- Only add custom listeners when they reset mutated state or close external handles
