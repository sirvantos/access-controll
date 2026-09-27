---
description: Deploy a release — verify branch, quality gates, bump composer version, tag, build deploy ZIP, merge to develop. Run /smartthings.release-deploy from the release branch after prepare.
---

# Smartthings Release — Deploy

## User Input

```text
$ARGUMENTS
```

You **MUST** consider the user input before proceeding. **No spec identifiers** are required for deploy; `{tag}` comes from the current **release branch** name.

## Goal

Finalize an already prepared release on a **release branch**: ensure `composer.json` version matches `{tag}`, **tag** the release, **build** the deploy archive, **merge** back to `develop`. Assumes **`releases/{tag}.md`** (and release content) are already in place — typically after **`/smartthings.release-prepare`**.

## Command Format

```text
/smartthings.release-deploy
```

Optional: user may add notes in natural language (e.g. confirm tag message); still derive `{tag}` only from the branch name unless they explicitly override with a full release branch checkout.

## Pre-flight

```bash
git status --porcelain
git branch --show-current
```

Rules:

- Working tree must be **clean**.
- Current branch must match `release/v{tag}` or `release/{tag}`; extract **`{tag}`** (e.g. `release/v1.4.2` → `1.4.2`).

If any check fails, stop and report the exact failure.

## Step 1 — Quality gates

Run before tagging or building:

```bash
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run test
```

Stop immediately if any gate fails.

## Step 2 — Update version in `composer.json`

Set `version` to `{tag}`:

```bash
jq '.version = "{tag}"' composer.json > composer.json.tmp && mv composer.json.tmp composer.json
```

- If `version` is **already** `{tag}` and there is nothing to commit, **skip** the commit below.

Otherwise commit:

```bash
git add composer.json
git commit -m "release: bump version to {tag}"
```

Ask for **explicit user confirmation** before committing the version bump (if a commit is needed).

## Step 3 — Tag release branch

Before tagging:

- Show recent `git log --oneline -20` on the release branch.
- Ask for **explicit user confirmation**.

Create annotated tag **`v{tag}`** (e.g. `v1.4.2`):

```bash
git tag -a "v{tag}" -m "Release v{tag}: {summary}"
```

`{summary}` = one short line from `releases/{tag}.md` (Обзор / первые пункты) or from the latest release notes commit.

- If tag **`v{tag}` already exists** locally, **stop** and report; do not force-retag without explicit user instruction.

## Step 4 — Build deploy artifact

Before running the script:

- Confirm **`BACKUP_PASSWORD`** is exported or present in the local ignored `.env`.
- Ask for **explicit user confirmation**.

Then:

```bash
chmod +x scripts/build_deploy_zip.sh
./scripts/build_deploy_zip.sh
```

## Step 5 — Merge back to `develop`

After user confirms they want to merge (destructive on `develop` if conflicts):

```bash
git checkout develop
git pull --ff-only origin develop
git merge --no-ff "release/v{tag}" -m "release: merge release/v{tag} back to develop"
```

Use the **actual** release branch name if it differs (e.g. `release/1.4.2` without `v`).

## Safety rules

- Never run deploy from a non-release branch.
- Never skip quality gates before tag/build.
- Never create/move tags or run the deploy script without explicit user approval.
- Never delete the release branch after deployment (unless the user explicitly asks).
- Avoid destructive git operations on shared branches without explicit approval.

## Confirmation gates (deploy)

Ask for explicit approval before:

1. Committing `composer.json` version bump (when it changes).
2. Creating the annotated tag.
3. Running `scripts/build_deploy_zip.sh`.
4. Merging into `develop` (confirm branch name and that remote is intended).

## Failure handling

- Gate failure: stay on release branch, fix, re-run gates.
- Tag exists: user must delete/move tag or choose a different patch version — do not force by default.
- Merge conflicts: stop, report files; user resolves.

## Success report

Report:

- Released version `{tag}` and tag name `v{tag}`
- Path to `releases/{tag}.md` (expected to exist)
- Whether deploy archive was built (artifact path if the script prints it)
- Current branch after merge (`develop` if merge succeeded)
