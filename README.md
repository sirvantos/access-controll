# access-controll

## Verify

`make verify` is green when it exits 0. It runs these guards in order and stops on the first failure:

- **Node** — requires `^20.19.0` or `>=22.12.0` before the frontend steps.
- **fmt** — `pint --test` for PHP and `prettier --check` for Vue and TypeScript. Checks formatting and does not rewrite files.
- **lint** — Larastan at level 8, then deptrac for the layer rules (`Http`, `Health`, `HealthPublicApi`).
- **build** — `vue-tsc --noEmit`, then the Vite production build.
- **test** — Pest through the HTTP stack, then Vitest for the Vue component.

Node must be `^20.19.0` or `>=22.12.0` (`.nvmrc`):

```bash
nvm use
make verify
```

## Factory

The factory contract is [`factory/factory.yaml`](factory/factory.yaml). Prompts for the implementation loop are in [`factory/prompts/`](factory/prompts/).

A feature runs in this order: specify, clarify, plan, tasks, analyze, implement, converge. `clarify` stops the run when questions remain. `analyze` is read-only and blocks implementation when the spec, plan, and tasks disagree. `implement` takes one task at a time; the orchestrator runs `make verify`, then the quality reviewer and the functional reviewer. `converge` may only append to `tasks.md`, and then implementation runs again.

Models set in the contract:

- **spec_author** — `claude-opus-5-5-medium`
- **implementer** — `composer-2.5-fast`
- **reviewer** — `grok-4.7-high`

`reviewer` is a different model family from the implementer. The orchestrator that executes the contract is not in the repo yet.