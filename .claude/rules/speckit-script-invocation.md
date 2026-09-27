# Speckit Feature Resolution

- The active feature directory is the `feature_directory` value in `.specify/feature.json`, written by `/speckit-specify`.
- Prerequisite scripts (`.specify/scripts/bash/check-prerequisites.sh` and the other scripts that source `common.sh`) read that file. Do not set `SPECIFY_FEATURE` to force a `NNN-name` branch match.
- The git branch name and the spec directory name are independent. A branch such as `feature/CI-122` is valid while the spec lives in `specs/003-user-auth`.
- To work on a specific spec in this session, set `SPECIFY_FEATURE_DIRECTORY` to the repo-relative path (for example `specs/003-user-auth`) for that command. The script persists that override back to `.specify/feature.json`.
- In the first progress update for a spec task, state the `feature_directory` value from `.specify/feature.json`.
