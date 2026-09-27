---
name: release-versioning
description: Manage application version metadata and customer-facing release communication for this repo. Use when changing the app version in `composer.json`, exposing version at runtime, or preparing release notes, deployment steps, and rollback guidance.
---

# Release Versioning & Deployment Communication

## Use this skill when

- You are changing the application versioning pattern
- You are exposing the app version at runtime or in the UI
- You are preparing release notes or release fragments
- You are documenting deployment steps, new env vars, migrations, or rollback instructions

## Do not use this skill when

- You are only implementing feature code with no release-process impact
- You are only updating OpenAPI or health/readiness probes
- You are only changing git commit behavior

## Related skills

- Git workflow: `.claude/skills/git/SKILL.md`
- Quality checks before release: `.claude/skills/quality-gates/SKILL.md`

## Goal

Keep application version metadata and customer-facing release communication consistent, accurate, and deployment-friendly.

## Scope

- `composer.json`
- Runtime version config, e.g. `config/version.php`
- Footer/version display wiring when required
- `releases/**`
- Release fragments under `releases/unreleased/**`

## Workflow

### 1) Treat `composer.json` as the canonical version source

- Store the current application version in the `version` field
- Ensure runtime code can read and display that value safely
- Handle missing or malformed values with a safe fallback such as `dev`

### 2) Keep runtime version access simple

- Read the version from `composer.json` through config
- Expose the version where the app needs it, such as footer/build metadata
- Avoid duplicating the version in multiple files without a strong reason

### 3) Maintain release fragments

- Each feature branch that introduces deployment-visible changes should add a fragment under `releases/unreleased/{branch-slug}.md`
- Include migrations, new env vars, config changes, manual steps, and rollback notes when applicable
- Keep fragments branch-scoped to reduce merge conflicts

### 4) Cut a release cleanly

- Bump the application version
- Copy `releases/TEMPLATE.md` to `releases/{version}.md`
- Consolidate unreleased fragments into the final release file
- Remove consumed fragments and preserve `.gitkeep` if present
- Ensure deployment steps and rollback guidance are explicit for customers

## Release content checklist

- What changed
- Migration file names and whether they are destructive
- New `.env` variables with copy-pasteable snippets
- Post-deploy commands in execution order
- Rollback instructions
