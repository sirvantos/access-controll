---
description: (Router) Release workflow split into prepare + deploy. Use /smartthings.release-prepare then /smartthings.release-deploy.
---

# Smartthings Release (split)

The release workflow is **two commands**:

| Step | Command | Purpose |
|------|---------|---------|
| 1 | **`/smartthings.release-prepare`** `<spec1,spec2,...>` | Pre-flight, resolve specs, write `releases/{tag}.md`, quality gates, **commit** release notes |
| 2 | **`/smartthings.release-deploy`** | Quality gates, **`composer.json`** version, **tag** `v{tag}`, **build** deploy ZIP, **merge** to `develop` |

A **logically separate** third command closes a whole release series (not a replacement for prepare/deploy):

| When | Command | Purpose |
|------|---------|---------|
| End of a series (e.g. 5.x) | **`/speckit.finalize_release_docs`** | Reconcile/backfill `releases/{ver}.md` for every sub-release, update living `docs/`, generate the client closing pack in `temp/release-docs/` |

- `{tag}` is always taken from the **current release branch** (e.g. `release/v1.4.2` → `1.4.2`).
- Prepare **requires** a comma-separated list of spec folder names and/or feature-branch ids (see **`smartthings.release-prepare`**).
- Deploy **does not** take spec ids; run it from the same release branch after prepare (and after a clean tree).

If the user invokes **`/smartthings.release`** with arguments, tell them to use **`/smartthings.release-prepare`** with the same identifier list, then **`/smartthings.release-deploy`** when ready.

For full step text, open:

- `.cursor/commands/smartthings.release-prepare.md`
- `.cursor/commands/smartthings.release-deploy.md`
