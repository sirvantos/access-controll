# access-controll

## Manual testing

Load the sample companies and users:

```bash
php artisan migrate
php artisan db:seed
```

Every account below uses the password `password`. Sign in at `/sign-in`.

| Role | Email | Company | After sign-in |
| --- | --- | --- | --- |
| Super admin | `owner@example.com` | — | `/companies` |
| Company admin | `admin@acme.test` | Acme | `/company/users` |
| Viewer | `viewer@acme.test` | Acme | `/` |
| Company admin | `admin@globex.test` | Globex | `/company/users` |
| Viewer | `viewer@globex.test` | Globex | `/` |

Acme also has a pending viewer invitation for `invitee@acme.test`. Open:

```text
/invitation/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef
```

The link is valid through 2030-01-15. Running `db:seed` again leaves these rows in place.

## Verify

`make verify` is green when it exits 0. It runs these guards in order and stops on the first failure:

- **Node** — requires `^20.19.0` or `>=22.12.0` before the frontend steps.
- **fmt** — `pint --test` for PHP and `prettier --check` for Vue and TypeScript. Checks formatting and does not rewrite files.
- **lint** — Larastan at level 8, then deptrac. Layers are `Http`, `Support`, `Exceptions`, `Rules`, `Providers`, and each directory under `app/Modules` together with its `PublicApi`.
- **build** — `vue-tsc --noEmit`, then the Vite production build.
- **test** — Pest through the HTTP stack, then Vitest for the Vue component.

Node must be `^20.19.0` or `>=22.12.0` (`.nvmrc`):

```bash
nvm use
make verify
```

## Factory

The contract is [`factory/factory.yaml`](factory/factory.yaml). Prompts for the implementation loop are in [`factory/prompts/`](factory/prompts/). The orchestrator is the Composer package `access/factory`, installed from the sibling repository `../factory` and run as `vendor/bin/factory`.

Run it with Node 22 on `PATH`, because each task runs `make verify`. `cursor-agent` must be logged in.

```bash
nvm use
vendor/bin/factory run -v -i specs/001.md
vendor/bin/factory run --fast -v -i specs/spa-visual-style-kit.md
vendor/bin/factory resume -v
```

A one-line description still works: `vendor/bin/factory run "..."`.

`--fast` keeps the DevSpec path including **analyze**, but skips `make verify` and the quality/functional code reviewers after each implement wave. You review the code yourself. The mode is stored in `state.json`, so `resume` keeps it.

`-v` / `--verbose` prints every orchestrator action plus the live agent and verify output. Without `-v`, the terminal still shows stage milestones (step start/done, task wave progress, analyze/review outcomes, converge summary). The same milestone lines and the detailed action lines are always appended to `factory/runs/orchestrator.log`, so `tail -f factory/runs/orchestrator.log` shows the full trace. Agent replies and verify output for one attempt stay in `factory/runs/<step>/attempt-N/agent.log`.

### What `run` takes

The input is a feature brief. Pass it as one quoted argument, or point `-i` / `--input` at a markdown file. `specs/001.md` is only the brief. The factory still creates the next `specs/NNN-short-name/` directory and writes `spec.md` there. A file in `specs/` does not consume a feature number; numbering looks at directories.

The short name is up to four ASCII words from the start of the brief. Begin the file with an English heading when the rest of the text is in another language, otherwise the branch is named `NNN-feature-request`.

`run` starts from the git branch that is checked out. It allocates the next `specs/NNN-short-name/` directory, writes the spec template, and records the directory in `.specify/feature.json`. That short name is the feature branch. `feature.json` stays on this checkout: Spec Kit gitignores it, and the orchestrator copies it into each worktree so the agent can read the active directory.

If that feature already has `specs/<feature>/.factory/state.json` and the run is not finished (`next` is not `done`), `run` stops and `resume` is the way to continue. After `done`, another `run -i …` starts the next feature directory.

Author agents run with `cursor-agent --force`, so CLI deny rules are not a hard block. After each author, implementer, or converge step, the orchestrator reverts any change under protected paths (for example `factory/**`) and keeps allowed spec files. A stray edit to `factory/factory.yaml` no longer discards the whole plan worktree.

### What `run` does

The happy path does not pause. The same checkout stays on the feature branch. Each agent works in its own worktree and does not run git.

1. **specify** — `spec_author` fills `spec.md`.
2. **clarify** — the same role resolves open questions. A `spec_gap` writes `specs/<feature>/.factory/questions.md` into the checkout and stops. That file stays uncommitted until analyze accepts the spec.
3. **plan** — writes `plan.md` and the design notes the skill requires.
4. **tasks** — writes `tasks.md`. Task lines look like `- [ ] T014 [US1] Implement the service (depends on T012, T013)`.
5. **analyze** — `reviewer` checks spec, plan, and tasks and does not edit files. Findings at `critical`, `high`, or `medium` are passed to `spec_editor`, continuing that model's chat. The run does not stop for a person. Analyze checks the edit again, up to the same retry limit as a task. `low` findings are skipped. The run stops only when a finding is still open after that limit.
6. **implement** — up to five open tasks at a time. A task joins the group when every task it depends on is already done or earlier in the same group. Each attempt runs two agents on that group: feature tests (`feature_tester`), then production code and unit tests (`implementer`). The orchestrator then runs `make verify` once, then the quality reviewer and the functional reviewer. Both reviewers run after a green verify, including when quality requests changes, and the next attempt receives both findings. `implement_loop.review_mode` is `strict` (default), `balanced`, or `soft`: strict blocks on assumptions and every kept finding; balanced surfaces assumptions and blocks only critical/high issues; soft ignores assumptions and blocks only `Prohibitions` / `Definition of Done` issues and scenario `fail`. The functional reviewer checks only the scenarios named by the tasks in the group. A finding about another task is left for that task, and the implementer does not build it. Three attempts. A `spec_gap` stops the run. A passed group is kept on the draft branch and each of its checkboxes becomes `- [x]`. In `--fast` mode, verify and both code reviewers are skipped after a successful implementer `done`.
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
└── 001-gate-pass            feature branch, two commits
    ├── spec …               after analyze accepts the spec
    └── implement …          after every task and converge are done
```

Draft work stays on `factory/draft`. Each step uses a worktree at `.worktrees/<step>` on `factory/<step>`, branched from `factory/draft` when that branch exists. `node_modules` and `.env` are symlinks. `vendor` is installed in the worktree with `composer install`, so PHP, PHPStan, and Composer resolve that tree alone. A failed `make verify` is written to `factory/runs/<task>-verify/attempt-N/agent.log`. The next implementer attempt receives that log, the reviewer issues, or the assumptions that blocked approval. When that chat resumes and the attempt stayed on the branch, this note is the whole prompt. A discarded attempt that never committed receives the original task prompt again. The note stays in `factory/runs/<task>/feedback.json`, so the first attempt after `resume` still sees it. The worktree gets a role-specific `.cursor/cli.json`. That file is restored before the step is recorded, so the tracked deny list stays as it is in the repo. Uncommitted edits in the checkout, such as an answer in `questions.md` or a fix in `tasks.md`, are copied into the next worktree.

The agent commits nothing. The orchestrator commits inside the worktree and moves `factory/draft` to that commit. The feature branch receives a commit at two points only. Analyze accepting the spec squash-merges the draft into one `spec <feature>` commit. The run finishing after converge squash-merges the remaining draft into one `implement <feature>` commit. `state.json` rides along in those commits. A passed task stays on the draft until the feature is done. Failed attempts stay on `factory/T001` for one task, or `factory/T005-T006` for a group, and the next attempt resumes that branch. A diff left by a reviewer discards the verdict.

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

- **spec_author** — `claude-opus-5-5-medium`, used for specify and clarify. Those two steps share one chat.
- **spec_editor** — `cursor-grok-4.6-high`, used for plan, tasks, and analyze repairs. Those steps share a second chat. A chat stays on one model, so plan does not resume the Opus chat.
- **feature_tester** — `cursor-grok-4.6-medium`, writes the feature tests for the current implement wave
- **implementer** — `gpt-6.1-luna-high`, writes production code and unit tests after those feature tests
- **reviewer** — `grok-4.7-medium`, used for analyze, both reviews, and converge

`reviewer` is a different model family from the coding implementer.

### Later: run without the laptop

Not built. The idea is to run `vendor/bin/factory` on a Cursor cloud machine, so a closed laptop does not stop the happy path. The cloud agent is only the machine. The orchestrator still calls `cursor-agent`, worktrees, and `make verify`.

A remote run still stops for a person on `unresolved_questions`, `analyze_failure`, and `retries_exhausted`. Those stops are useful only if the machine leaves a durable handoff before it disappears: the feature branch pushed, `state.json` saved, and either `questions.md` or the reviewer feedback available to the next session. Today that state stays on the machine that ran the process (`state.json`, an uncommitted `questions.md`, `factory/draft`, and gitignored `factory/runs/`). A new cloud session cannot `resume` without it.
