# Speckit Boundary Values

- When a Speckit feature introduces shared boundary values (length limits, numeric ranges, enum-like external codes, pagination caps), create or update `specs/<feature>/constraints.md`.
- Treat that file as the feature's source of truth for limits referenced by `spec.md`, `plan.md`, `data-model.md`, `contracts/**`, implementation validation, OpenAPI, and tests.
- Do not duplicate bare numeric limits across spec artifacts and code without checking the helper first.
- When implementation requires a named constant for a helper value, name it after the business field and keep the value aligned with `constraints.md`.
- If a backend/storage constraint conflicts with the helper, update the helper and all dependent artifacts together, or raise the conflict before changing behavior.
