# API Resource Wrapping

- Only `OkResource` may set `public static $wrap = null`.
- All other `JsonResource` classes must keep the Laravel resource wrapper so successful API payloads are returned under `{ data: ... }`.
- Non-model operation Resources, version Resources, and collection Resources should also stay wrapped; do not copy the `OkResource` unwrapped pattern.
