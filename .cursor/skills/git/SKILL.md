---
name: git
description: Git workflow and commit conventions for this project. Use when working with git, committing changes, or creating branches.
---

# Git Workflow

## NON-NEGOTIABLE RULE: Always Ask Before Committing

**NEVER commit without explicit human confirmation.** After running quality gates and reviewing changes, ask the user if they want to commit.

This is non-negotiable - do not proceed with git add/commit until the user explicitly approves.

## Commit Rules

### When to Commit

- **Only commit when the task is fully complete**. This means:
  - All tests pass (`php artisan test`)
  - PHPStan passes (`vendor/bin/phpstan analyse`)
  - Pint passes (`vendor/bin/pint --dirty`)

### Commit Message Format

Write clear, concise commit messages that describe what was done:

```
<type>: <short description>

<optional detailed description>
```

**Good examples:**
```
feat: add managers list endpoint validation

- Add ListManagersRequest with pagination/search validation
- Create ListManagersData DTO with Stringable fields
- Update controller to use DTO pattern
```

```
fix: resolve phone normalization in profile update

- Use prepareForValidation to normalize before validation
- Update UpdateUserData to accept Stringable phone
```

**Bad examples:**
```
Fixed some stuff
```

```
Update file.php - this changes things in the DTO and also fixes a bug in the controller and also adds new validation
```

### Commit Message Guidelines

- Use imperative mood ("add" not "added" or "adds")
- First line: max 72 characters
- Be specific about what changed
- Mention related files or features when relevant


## Before Commit Checklist

1. Run quality gates:
   ```bash
   vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
   vendor/bin/pint --dirty
   php artisan test
   npm test
   ```

2. Review changes:
   ```bash
   git diff --stat
   ```

3. **Ask user for confirmation** - present the changes and ask "Do you want me to commit these changes?" with the proposed commit message.

## Automated Commit Command

When the user asks to commit, run the full quality gate pipeline first:

```bash
# Run all quality gates
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G && vendor/bin/pint --dirty && php artisan test && npm test

# If all pass, then show git diff and ask for confirmation
git diff --stat
```

Then ask the user to confirm before running `git add` and `git commit`.
