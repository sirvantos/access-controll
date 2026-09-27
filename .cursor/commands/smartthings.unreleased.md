---
description: Create an unreleased fragment for the current feature branch in releases/unreleased/
handoffs: []
---

## Create Unreleased Fragment

This command creates a new unreleased release note fragment based on the current feature branch.

### Steps

1. **Get current branch name**
   ```bash
   git branch --show-current
   ```

2. **Find the feature spec**
   - Look for `specs/{branch}/spec.md` to understand what changed
   - If not found, look for any file in `specs/{branch}/`

3. **Analyze changes**
   ```bash
   git diff main...HEAD --stat --name-only
   ```
   This shows all files changed in the feature branch.

4. **Determine what changed**
   - Check for new API endpoints (routes/api.php)
   - Check for new migrations (database/migrations/)
   - Check for new environment variables (.env.example)
   - Check for new components/pages (resources/js/)

5. **Read the FRAGMENT_TEMPLATE.md**
   ```bash
   cat releases/FRAGMENT_TEMPLATE.md
   ```

6. **Create the unreleased fragment**
   - Use format: `releases/unreleased/{branch}.md`
   - Fill in sections based on what changed:
     - **Что изменилось**: List new features/changes
     - **Миграции**: List any new migrations
     - **Новые переменные окружения**: List any new .env vars
     - **Действия после развёртывания**: Any deploy commands needed
     - **Примечания**: Any additional notes

7. **Show the created file and ask for confirmation to commit**

### Example Output

```
Creating unreleased fragment for branch: 024-campaign-progress

Changes detected:
- New API field: leads_processed_percentage
- New frontend component: CampaignLeadsProgress.vue
- No migrations required
- No new environment variables

Created: releases/unreleased/024-campaign-progress.md

---Content---
# 024 — Индикатор прогресса обработки лидов

## Что изменилось
...
---Content---

Commit this fragment? (y/n)
```

### Rules

- **ALWAYS ask for confirmation** before committing
- If no spec folder exists, ask the user what the feature does
- Follow the existing FRAGMENT_TEMPLATE.md format
- Use Russian language for the fragment content (as per existing fragments)
- Include specific technical details (endpoint paths, component names, etc.)
