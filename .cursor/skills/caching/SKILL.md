---
name: caching
description: Add or change application caching safely for Laravel/Redis-backed data. Use when introducing caching, cache invalidation, cache key design, or hydrating cached model attributes without caching full Eloquent models.
---

# Caching discipline (Redis-first)

Use this when you intentionally introduce caching. Default is **no caching** unless justified.

## Related skills
- Events: `.cursor/skills/events/SKILL.md` (for cache invalidation via events/listeners)

## Scope (where caching code lives)

- Actions/Services: `app/Actions/**`, `app/Services/**`
- Models/Observers (invalidation): `app/Models/**`, `app/Observers/**`
- Providers (cache tags/bindings): `app/Providers/**`

## When to cache
- Clear win on read-heavy paths (perf/latency) with acceptable staleness.
- Data shape is stable or versioned (e.g., `:v2` suffix) and has a defined invalidation plan.

## Rules (from constitution)
- **Never cache Eloquent model objects.** Cache raw attribute arrays only (`$model->getAttributes()`).
- Hydrate via `Model::newFromBuilder($attrs)` (single) or `Model::hydrate($collection)` (multiple). Reattach relations with `setRelation()` if needed.
- Do not cache eager-loaded relations as nested models; store relation attributes separately. Avoid deep nesting (>2 levels).
- Use intentional keys with prefixes and versions: `user:{id}:v2`, `product:{id}:stock`.
- Prefer tagged cache for grouped invalidation: `Cache::tags(['user', $userId])`.
- Invalidate on create/update/delete via observers/events; do not rely solely on TTL for correctness.
- Avoid static property caches (Octane safety) and automatic model caching packages.

## Workflow
1) Decide scope + shape: attributes only, with explicit version suffix.
2) Write through reads:
   - `Cache::remember(key, ttl, fn () => $model->getAttributes());`
3) Hydrate on read:
   - `$attrs = Cache::get(key);`
   - `User::newFromBuilder($attrs);`
4) Relations: cache separately per relation; re-link with `setRelation()`.
5) Invalidate:
   - On mutate: observers/events clear relevant keys/tags.
   - On schema/shape change: bump version suffix in key.

## Redis specifics
- Batch ops with `Redis::pipeline()` when writing many keys.
- For structured data needing partial updates, prefer `hset`/`hget`.
- Monitor memory / `maxmemory-policy` (e.g., `allkeys-lru`)—keep payloads lean.

## Anti-patterns (do NOT do)
- `cache()->put("user:{$id}", User::find($id));` (caches full model)
- `cache()->put("user:{$id}", User::with('posts')->find($id));` (caches nested relations)
- Storing cache results in static properties across requests.

## Testing
- Fake cache in tests (`Cache::shouldReceive()` or rely on in-memory) but assert invalidation paths.
- Validate hydration paths (attributes → model) and relation reattachment if used.
