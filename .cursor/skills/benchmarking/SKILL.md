---
name: benchmarking
description: Performance benchmarking discipline using Laravel's Benchmark class. Use when profiling critical bottlenecks—never in production code.
---

# Benchmarking Discipline

## When to Benchmark

- **AVOID** benchmarking in regular code
- Only use when profiling **critical performance bottlenecks**
- **NEVER** add benchmarking to every method or action

## How to Benchmark

**ALWAYS use `Illuminate\Support\Benchmark`**—never manual `microtime()` calculations.

## Methods

```php
// Single operation timing
Benchmark::measure(fn () => $operation);

// Development debugging
Benchmark::dd(fn () => $operation);

// Result + timing
[$result, $duration] = Benchmark::value(fn () => $operation);
```

## Cleanup

- **REMOVE all benchmarking code after investigation**
- Benchmarking is for **diagnosis, NOT production**

## Ongoing Monitoring

**PREFER** these tools for continuous monitoring:
- Laravel Telescope
- Laravel Debugbar
- APM tools (New Relic, Datadog)
