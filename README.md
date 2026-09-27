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

The factory contract is [`factory/factory.yaml`](factory/factory.yaml). Prompts for the implementation loop are in [`factory/prompts/`](factory/prompts/). The orchestrator is `php factory/orchestrate.php`.

A person passes a feature description. The factory runs specify, clarify, plan, tasks, analyze, implement, and converge. `specify` writes `spec.md`. The happy path does not pause. It stops to answer clarify questions, to fix an analyze failure, or when a task has used its three attempts. `resume` continues after that stop. `analyze` is read-only. `implement` takes one task at a time; the orchestrator runs `make verify`, then the quality reviewer and the functional reviewer. `converge` may only append to `tasks.md`, and then implementation runs again, at most twice. The factory leaves the result on the feature branch. It does not merge to `main` and it does not open a pull request.

Run it with Node 22 on `PATH`, because each task runs `make verify`:

```bash
nvm use
php factory/orchestrate.php run "Сотрудник отмечает проход через турникет картой. Система пишет событие и отдаёт его в GET /api/v1/passes за сегодня."
php factory/orchestrate.php resume
```

Commands:

- `vet` — checks models, `.cursor/cli.json`, and the task graph.
- `graph` — prints tasks in dependency order.
- `run "description"` — creates the feature directory and runs from specify.
- `status` — prints the feature directory and the current step.
- `resume` — continues after a stop. Clarify questions are reread from `questions.md`.
- `stale` — lists done tasks whose spec or plan changed after they were merged.
- `unlock` — clears a lock whose process is gone.

State is `specs/<feature>/.factory/state.json`. Agent logs are `factory/runs/<step>/attempt-N/`. `cursor-agent` runs inside `.worktrees/`, with `vendor`, `node_modules`, and `.env` linked from this checkout.

Models set in the contract:

- **spec_author** — `claude-opus-5-5-medium`
- **implementer** — `composer-2.5-fast`
- **reviewer** — `grok-4.7-high`

`reviewer` is a different model family from the implementer.