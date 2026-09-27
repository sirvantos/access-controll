---
description: Run quality gates (PHPStan, Pint, PHP tests, npm tests) then commit changes if all pass
handoffs: []
---

## Run Quality Gates & Commit

This command runs all quality gates and, if they all pass, shows git diff and asks for confirmation to commit.

### Steps

1. **Run PHPStan**
   ```bash
   vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
   ```

2. **Run Pint**
   ```bash
   vendor/bin/pint --dirty
   ```

3. **Run PHP Tests**
   ```bash
   php artisan test --compact
   ```

4. **Run npm Tests**
   ```bash
   npm test
   ```

5. **If all pass, show git diff**
   ```bash
   git diff --stat
   ```

6. **Ask user for confirmation** to commit with a proposed commit message.

### Rules

- **NEVER commit without explicit human confirmation**
- If any quality gate fails, report the failure and do NOT proceed with commit
- Use the standard commit message format from the git skill
