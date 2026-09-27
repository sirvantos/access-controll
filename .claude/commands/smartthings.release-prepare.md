---
description: Prepare a release — release notes from specs, quality gates, commit releases/{tag}.md. Run from a clean release branch. Use /smartthings.release-prepare <spec1,spec2,...>
---

# Smartthings Release — Prepare

## User Input

```text
$ARGUMENTS
```

You **MUST** consider the user input before proceeding.

## Goal

Generate `releases/{tag}.md` from specification folders (or resolved feature-branch specs), run quality gates, and commit the release notes. **Does not** bump version, tag, build ZIP, or merge to `develop` — use **`/smartthings.release-deploy`** after this.

Run from a **release branch** (e.g. `release/v1.4.2`). Version `{tag}` is taken from the branch name: `release/v1.4.2` → `1.4.2`.

## Command Format

```text
/smartthings.release-prepare <identifier1,identifier2,...>
```

Examples:

```text
/smartthings.release-prepare 031-contact-phone-history,030-custom-import-columns
/smartthings.release-prepare CI-18,CI-21
/smartthings.release-prepare feature/CI-18,feature/CI-21
```

### Parsing rules

- `<identifiers>` is a **mandatory** comma-separated list of spec references.
- Each identifier can be:
  - **Spec folder name** (e.g. `031-contact-phone-history`) → `specs/{identifier}/` must exist.
  - **Feature branch id** (`CI-18`, `feature/CI-18`) → normalize to `CI-18`, search all `specs/*/spec.md` for `**Feature Branch**:` matching `` `feature/CI-18` `` or `` `CI-18` ``.

If the input does not match this shape, stop and show this usage string.

If any identifier cannot be resolved, stop and report:

```text
Cannot find spec for identifier: {identifier}
Searched for: specs/{identifier}/ OR feature branch matching '{identifier}'
```

## Phase: Prepare Release Notes

### 1. Pre-flight

```bash
git status --porcelain
git branch --show-current
```

Rules:

- Working tree must be **clean**.
- Current branch must match `release/v*` or `release/*`.
- Derive `{tag}` from the branch name (`release/v1.3.4` → `1.3.4`).

If any check fails, stop and report the exact failure.

### 2. Resolve spec identifiers

Follow the parsing rules above for each identifier.

### 3. Generate `releases/{tag}.md`

For each resolved spec folder:

1. Read all `.md` files under `specs/{spec-name}/`.
2. Extract: title (spec header), features (User Stories, P1/P2), migrations (`data-model.md` / Key Entities), env vars (Assumptions / Requirements), notes (Edge Cases / Assumptions).

Aggregate into `releases/{tag}.md` using `releases/TEMPLATE.md`.

Section mapping:

- User Stories (P1/P2) → `## Новые возможности`
- Bug fixes → `## Исправления`
- Key Entities / `data-model.md` → `## Миграции базы данных`
- Env vars → `## Новые переменные окружения (.env)`
- Edge Cases / Assumptions → `## Примечания`

Title line:

```text
# Версия {tag} — {DD.MM.YYYY}
```

### 4. Quality gates

Run and **stop if any fails**:

```bash
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run test
```

### 5. Commit release notes

Before committing:

- Show the generated `releases/{tag}.md`.
- Ask for **explicit user confirmation**.

Then:

```bash
git add releases/{tag}.md
git commit -m "release: add {tag} release notes from specs"
```

### Success report

Report:

- `{tag}`
- Resolved spec folder names
- Path `releases/{tag}.md`
- Current branch

Tell the user:

> Release notes for `{tag}` are ready and committed. Run **`/smartthings.release-deploy`** to bump version (if needed), tag, build the deploy archive, and merge back to `develop`.

## Safety

- Do not run on a non-release branch.
- Do not skip quality gates.
- Do not commit release notes without explicit user approval.

## Failure handling

- Resolve failures: fix identifiers or add/update specs; re-run prepare.
- Gate failures: fix code/tests; re-run gates then prepare.
