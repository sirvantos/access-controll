---
name: rabbitmq-consumers
description: Add or change RabbitMQ AMQP consumers (connection profiles, consumer bindings, handlers, RabbitHandlerFactory wiring, tests, deployment env). Use when adding a new rabbit:consume consumer, RabbitMQ handler, connection profile, expected_reply_to binding, or extending the universal worker beyond depersonalization.
---

# RabbitMQ Consumers (AMQP worker)

Use this skill when adding or changing **AMQP consumers** run via `php artisan rabbit:consume {consumer}`. This is **not** Laravel Queue (`ShouldQueue` jobs) — see `.cursor/skills/queues/SKILL.md` for that.

## Architecture (do not reinvent)

```
config/rabbitmq.php
├── connections.{profile}   ← broker cluster, TLS, backoff, logging
└── consumers.{name}        ← vhost, queue, handler, prefetch, expected_reply_to, connection

rabbit:consume {name}  →  RabbitConsumeCommand
  → ConsumerConfigValidator
  → RabbitHandlerFactory::make($consumerConfig)
  → Consumer (connect, consume, ack/nack, reconnect)
```

- **One OS process = one consumer name** (Supervisor/systemd). Example: `rabbit:consume depersonalize`.
- **Topology is external** — the app does not declare exchanges/queues in production. Only consume + optional reply publish.
- **Reuse before create** — extend `Consumer`, `MessageDispatcher`, `ConnectionFactory`, `RabbitHandlerFactory`; do not add a second worker framework.

## Scope

| Layer | Path |
|-------|------|
| Config | `config/rabbitmq.php` |
| Worker | `app/RabbitMq/**`, `app/Console/Commands/RabbitConsumeCommand.php` |
| Handlers | `app/Handlers/{Domain}/**` |
| Business logic | `app/Actions/**` (handlers stay thin) |
| Tests | `tests/Feature/RabbitMq/**`, `tests/Unit/RabbitMq/**`, `tests/Unit/Handlers/**` |
| Test helpers | `tests/Support/RabbitMq/**` |
| Docs (when env/deploy changes) | `.env.example`, `docs/DEPLOYMENT_server.md`, `releases/2.x.md` |

Canonical background: `specs/052-simple-rabbit-mq-client/quickstart.md` (handler shapes), `specs/055-rabbitmq-depersonalize-cluster/` (connection profiles + TLS).

## Checklist: add a new consumer

Copy and track progress:

```
- [ ] 1. Connection profile (if new broker/cluster)
- [ ] 2. Consumer binding in config/rabbitmq.php
- [ ] 3. Handler class (HandlerInterface)
- [ ] 4. RabbitHandlerFactory branch (only if handler needs consumer-config values)
- [ ] 5. Env vars + .env.example
- [ ] 6. Tests (config smoke + handler unit + optional feature pipeline)
- [ ] 7. Deployment doc / release note (if operator-facing)
- [ ] 8. Larastan → Pint → targeted Pest
```

---

## Step 1 — Connection profile (new cluster only)

Add `connections.{profile}` in `config/rabbitmq.php`. Follow the `depersonalization` profile shape:

- `nodes` — up to 3 slots; include a node only when `env('RABBITMQ_{PREFIX}_HOST')` is filled (no default hosts for empty slots).
- `user`, `password`, `heartbeat`, `read_write_timeout`
- `backoff` — `initial_ms`, `max_ms`, `multiplier`
- `log_ack_sample_rate`, `counter_dump_seconds`, `max_connection_attempts`
- `ssl` — `enabled`, `cafile`, `cert`, `key`, `passphrase`, `verify_peer`, `verify_peer_name`, `allow_self_signed`
- TLS material: CA required only when `verify_peer=true`; client cert/key required only when paths are set (mTLS). `allow_self_signed` is per-profile (`*_SSL_ALLOW_SELF_SIGNED`, default false).

Env prefix pattern: `RABBITMQ_{PROFILE_PREFIX}_*` (e.g. `RABBITMQ_DEPERSONALIZE_*`). **Do not** fall back to legacy generic `RABBITMQ_*` for named production profiles.

Failover: `ConnectionFactory` tries nodes from index 0 on each connect/reconnect.

### TLS material (mTLS)

- Prefer the **directory convention**: one `RABBITMQ_{PREFIX}_SSL_DIR` per profile resolves to `{dir}/ca.pem`, `{dir}/client.crt`, `{dir}/client.key` via `App\Support\RabbitMq\SslMaterialPathResolver`. Explicit `SSL_CAFILE`/`SSL_CERT`/`SSL_KEY` still work and apply only when `SSL_DIR` is empty.
- **Production**: drop the bank-provided `ca.pem`/`client.crt`/`client.key` into the profile dir. Key mode `640`, readable by `www-data`.
- **Dev stand** (simulate the bank): `./docker/local_deploy.sh` or `docker/rabbitmq/tls/generate-certs.sh --all` + compose overlay `docker-compose.rabbit.tls.yml`. Generated material uses `644` on bind-mounted files so `rabbitmq`/`www-data` in containers can read keys. See `docker/rabbitmq/tls/README.md`.
- After regenerating certs, restart the worker (PHP holds the TLS context): `supervisorctl restart rabbit-{name}` or `docker compose restart crm-rabbit crm-backend`.

---

## Step 2 — Consumer binding

Add `consumers.{name}`:

```php
'consumers' => [
    'my-flow' => [
        'connection' => 'my-profile',           // key in connections.*
        'vhost' => env('RABBITMQ_MY_VHOST', 'client.my'),
        'queue' => env('RABBITMQ_MY_QUEUE', 'crm.my.flow'),
        'expected_reply_to' => env('RABBITMQ_MY_EXPECTED_REPLY_TO', 'result.my.flow'),
        'handler' => MyFlowHandler::class,
        'prefetch' => (int) env('RABBITMQ_MY_PREFETCH', 1),
    ],
],
```

**Required fields** (enforced by `ConsumerConfigValidator`): `connection`, `vhost`, `queue`, `handler`, `expected_reply_to`, `prefetch` (positive int).

`expected_reply_to` is required for **all** consumers today, even fire-and-forget handlers that never publish a reply — use a sensible placeholder or the broker contract value.

Reuse an existing `connection` profile when the new consumer shares the same broker cluster.

---

## Step 3 — Handler

Location: `app/Handlers/{Domain}/{Name}Handler.php`.

```php
final readonly class MyFlowHandler implements HandlerInterface
{
    public function __construct(
        private MyFlowAction $action,
        // consumer-specific values injected by RabbitHandlerFactory — NOT config()
    ) {}

    public function handle(MessagePayload $message, ReplyPublisher $reply): HandlerOutcome
    {
        // 1. Validate transport (correlation_id, reply_to, payload shape)
        // 2. Call Action with DTO/primitives
        // 3. Optionally $reply->publish([...]) for request/response flows
        // 4. return HandlerOutcome::Ack or HandlerOutcome::Nack
    }
}
```

### Handler rules

| Rule | Detail |
|------|--------|
| Thin transport | Validation + orchestration only; business rules in `app/Actions/**` |
| No HTTP | No Request/Response/Session |
| No `config()` for consumer binding | Consumer-specific strings (e.g. `expected_reply_to`) come from constructor args wired by `RabbitHandlerFactory` |
| No topology declare | Do not create queues/exchanges in handler or command |
| Ack/Nack semantics | Malformed message → `Nack` (no business reply). Success → `Ack`. Technical failures → propagate or `Nack` per existing dispatcher behavior |
| Logging | No raw PII in warning/error context |
| Outcomes | Use `HandlerOutcome::Ack` / `HandlerOutcome::Nack` |

### Two handler shapes

**Fire-and-forget** — do not call `$reply->publish()`. Return `Ack` after Action succeeds.

**Request/response** (depersonalize pattern) — validate `reply_to` against injected `expectedReplyTo`, then `$reply->publish(['status' => ...])`, return `Ack`.

See `specs/052-simple-rabbit-mq-client/quickstart.md` §2.2.1.

---

## Step 4 — RabbitHandlerFactory (consumer-specific DI)

**Default:** handlers with only container-resolvable dependencies need **no factory change** — `default => $this->container->make($handlerClass)`.

**When consumer config must be injected** (e.g. `expected_reply_to`), add a `match` arm in `app/RabbitMq/Support/RabbitHandlerFactory.php`:

```php
return match ($handlerClass) {
    DepersonalizeClientHandler::class => new DepersonalizeClientHandler(
        $this->container->make(DepersonalizeClientAction::class),
        (string) $consumerConfig['expected_reply_to'],
    ),
    MyFlowHandler::class => new MyFlowHandler(
        $this->container->make(MyFlowAction::class),
        (string) $consumerConfig['expected_reply_to'],
    ),
    default => $this->container->make($handlerClass),
};
```

### Anti-patterns (do NOT use)

- `AppServiceProvider::when($handler)->needs('$param')->give(...)` per handler — does not scale
- `config('rabbitmq.consumers.*')` inside handler — couples handler to config keys
- Singleton `MessageLogger` / `MessageDispatcher` bound to one profile — create per run in `RabbitConsumeCommand` via `MessageLoggerFactory::fromConnectionProfile()`

When `match` grows large (many handlers), extract `RabbitHandlerConfigurator` implementations — not before needed.

---

## Step 5 — Environment and docs

- Add prefixed env vars to `.env.example` with a rename/mapping table if replacing old keys.
- Document TLS cert paths, permissions (`640` on key), and supervisor unit in `docs/DEPLOYMENT_server.md` when production deploy changes.
- Add release note in `releases/2.x.md` for operator-visible changes.

---

## Step 6 — Tests

Minimum for a new consumer:

1. **Config smoke** — extend or mirror `tests/Feature/RabbitMq/AddingNewConsumerTest.php`: set `connections` + `consumers`, mock `ConnectionFactory`, assert `rabbit:consume {name}` succeeds.
2. **Handler unit** — `tests/Unit/Handlers/{Domain}/{Handler}Test.php`: build handler via `RabbitHandlerFactory::make([...])` or a local helper; cover Ack/Nack, reply publish, invalid payload.
3. **Factory unit** — if new `match` arm: `tests/Unit/RabbitMq/RabbitHandlerFactoryTest.php`.
4. **Validator** — if new required fields: `tests/Unit/RabbitMq/ConsumerConfigValidatorTest.php`.

Reuse test support:

- `Tests\Support\RabbitMq\FakeHandler` — generic handler
- `Tests\Support\RabbitMq\FakeConsumerChannel` — channel fake
- `Tests\Support\RabbitMq\DepersonalizationConnectionProfile` — connection profile fixture (copy pattern for new profiles)

Run:

```bash
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Unit/RabbitMq/ tests/Feature/RabbitMq/ tests/Unit/Handlers/
```

---

## Step 7 — Operations

Each consumer gets its own long-running process:

```ini
# supervisor example
command=php /path/to/artisan rabbit:consume my-flow
autostart=true
autorestart=true
```

Do not run multiple consumer names in one process. Scale horizontally by duplicate processes **only** when the queue contract allows competing consumers.

---

## Related skills

- Business logic in handlers → `.cursor/skills/action-layer/SKILL.md`
- Laravel queues (different system) → `.cursor/skills/queues/SKILL.md`
- Pest tests → `.cursor/skills/pest-testing/SKILL.md`
- Quality gates → `.cursor/skills/quality-gates/SKILL.md`
- Security (TLS, secrets) → `.cursor/skills/laravel-security/SKILL.md`

## Reference files

| File | Role |
|------|------|
| `app/Console/Commands/RabbitConsumeCommand.php` | Entry: validate → factory → Consumer |
| `app/RabbitMq/Support/RabbitHandlerFactory.php` | Consumer-config → handler instance |
| `app/RabbitMq/Support/ConsumerConfigValidator.php` | Required binding fields |
| `app/RabbitMq/Support/MessageLoggerFactory.php` | Per-profile logger |
| `app/RabbitMq/ConnectionFactory.php` | Multi-node + SSL connect |
| `app/RabbitMq/Contracts/HandlerInterface.php` | Handler contract |
| `config/rabbitmq.php` | Profiles + consumer registry |
