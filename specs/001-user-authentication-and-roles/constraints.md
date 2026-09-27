# Constraints: User Authentication and Roles

**Feature**: [spec.md](./spec.md)

Source of truth for the boundary values referenced by `spec.md` and by any later plan, contract, validation, or test for this feature. The values are common industry defaults chosen because the feature description did not set them (see spec Assumptions). Change a value here first, then update every dependent artifact.

## Passwords

| Name | Value | Used by |
|------|-------|---------|
| Password minimum length | 8 characters | FR-012 |
| Password maximum length | 128 characters | FR-012 |

Length is counted in characters as the user sees them, not bytes.

## Password guessing protection

| Name | Value | Used by |
|------|-------|---------|
| Failed sign-in attempts per account before block | 5 consecutive | FR-008, SC-008 |
| Account sign-in block period | 15 minutes | FR-008, SC-008 |
| Failed sign-in attempts per source before block | 20 within 15 minutes | FR-009 |
| Source sign-in block period | 15 minutes | FR-009 |
| Password reset requests per email address | 1 per 60 seconds | FR-010 |

A successful sign-in resets the per-account consecutive failure count.

## Link lifetimes

| Name | Value | Used by |
|------|-------|---------|
| Invitation link lifetime | 7 days from sending | FR-024 |
| Password reset link lifetime | 60 minutes from sending | FR-032 |

## Roles

| Code | Meaning | Assignable in UI |
|------|---------|------------------|
| `super_admin` | Service owner | No (console command only) |
| `company_admin` | Manages own company | Yes |
| `viewer` | Read-only on own company's events, timesheets, reports | Yes |
