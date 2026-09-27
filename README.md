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

The contract is [`factory/factory.yaml`](factory/factory.yaml). Prompts for the implementation loop are in [`factory/prompts/`](factory/prompts/). The orchestrator is `php factory/orchestrate.php`.

Run it with Node 22 on `PATH`, because each task runs `make verify`. `cursor-agent` must be logged in.

```bash
nvm use
php factory/orchestrate.php run "Сотрудник отмечает проход через турникет картой. Система пишет событие и отдаёт его в GET /api/v1/passes за сегодня."
php factory/orchestrate.php resume
```

### What `run` takes

The only argument is a feature description. The factory turns that sentence into a spec, a plan, tasks, and the code that satisfies them.

`run` starts from the git branch that is checked out. It allocates the next `specs/NNN-short-name/` directory, writes the spec template, and records the directory in `.specify/feature.json`. The short name is up to four ASCII words from the description. A description without ASCII words is named `feature-request`. The same name becomes the feature branch, created from the branch that was current when `run` started.

If that feature already has `specs/<feature>/.factory/state.json`, `run` stops and `resume` is the way to continue.

### What `run` does

The happy path does not pause. The same checkout stays on the feature branch. Each agent works in its own worktree and does not run git.

1. **specify** — `spec_author` fills `spec.md`.
2. **clarify** — the same role resolves open questions. A `spec_gap` writes `specs/<feature>/.factory/questions.md`, commits it, and stops.
3. **plan** — writes `plan.md` and the design notes the skill requires.
4. **tasks** — writes `tasks.md`. Task lines look like `- [ ] T014 [US1] Implement the service (depends on T012, T013)`.
5. **analyze** — `reviewer` checks spec, plan, and tasks. It may not change files. `changes_requested` stops the run before any code is written.
6. **implement** — one open task at a time, in dependency order. For each task the implementer writes code and tests, then the orchestrator runs `make verify`, then the quality reviewer and the functional reviewer. Three attempts. A `spec_gap` stops the run. A passed task is merged and its checkbox becomes `- [x]`.
7. **converge** — the reviewer may only append `tasks.md`. New task ids go back to implement. This repeats at most twice. No new tasks means the feature is done.

`resume` continues after a stop. Clarify questions are read again from `questions.md`. An analyze failure returns to analyze. Exhausted attempts return to implement, or to converge when that step was the one that stopped.

### What `run` leaves

Exit `0` prints the feature directory and `done`:

```text
specs/001-gate-pass done
```

Exit `2` prints a stop reason on stderr: `unresolved_questions`, `analyze_failure`, or `retries_exhausted`. Exit `1` is an orchestrator error.

On the feature branch:

- `specs/<feature>/` with `spec.md`, `plan.md`, `tasks.md`, and the other spec artifacts the author wrote
- the application code and tests for the finished tasks
- `specs/<feature>/.factory/state.json` with the current step, per-task attempts, and a hash of the spec used for each finished task

`factory/runs/<step>/attempt-N/agent.log` keeps the agent and verify output. `.worktrees/` is where the agents worked. Both directories are gitignored. The factory branch is left checked out. Nothing is pushed, and nothing is merged back to the branch `run` started from.

`stale` lists finished tasks whose `spec.md` or `plan.md` changed after their checkbox was marked. The hash ignores the checkbox itself, so marking `T012` done does not make `T013` stale.

### How git is organized

The feature branch is the only branch that remains. Agent branches are temporary.

```text
main                         branch that was current when run started
└── 001-gate-pass            feature branch, checked out in this repo
    ├── factory/specify      worktree .worktrees/specify, squash-merged, then deleted
    ├── factory/clarify
    ├── factory/plan
    ├── factory/tasks
    ├── factory/analyze      read-only; deleted with no merge
    ├── factory/T001         one task; squash-merged after verify and both reviews
    └── factory/converge     may append tasks.md, then deleted
```

For every step the orchestrator commits `state.json` on the feature branch, adds a worktree at `.worktrees/<step>` on `factory/<step>` from that HEAD, and symlinks `vendor`, `node_modules`, and `.env` into it. The worktree gets a role-specific `.cursor/cli.json`. That file is restored before the step is committed, so the tracked deny list stays as it is in the repo.

The agent commits nothing. The orchestrator commits inside the worktree, removes the worktree, squash-merges `factory/<step>` into the feature branch, and deletes `factory/<step>`. A successful task is one commit on the feature branch: the code, the `- [x]` checkbox, and `state.json`. Failed attempts stay on `factory/T001` and the next attempt resumes that branch. Analyze never squash-merges. A diff left by a reviewer discards the verdict.

A lock in `state.json` stores the orchestrator pid. A live pid blocks a second `run` or `resume`. `unlock` clears a lock whose process is gone.

### Commands

- `vet` — checks models, `.cursor/cli.json`, and the task graph.
- `graph` — prints tasks in dependency order.
- `run "description"` — creates the feature directory and runs from specify.
- `status` — prints the feature directory and the current step.
- `resume` — continues after a stop.
- `stale` — lists finished tasks whose spec or plan changed after they were merged.
- `unlock` — clears a lock whose process is gone.

Models set in the contract:

- **spec_author** — `claude-opus-5-5-medium`, used for specify, clarify, plan, and tasks
- **implementer** — `composer-2.5-fast`, used for one task at a time
- **reviewer** — `grok-4.7-high`, used for analyze, both reviews, and converge

`reviewer` is a different model family from the implementer.
