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

## Field lengths

| Name | Value | Used by |
|------|-------|---------|
| Email maximum length | 255 characters | FR-001, FR-007, invitations, console command |
| Company name maximum length | 255 characters | FR-019, company creation |

Both limits are the `string` column size, which is the storage limit. The spec sets neither value (see plan Assumptions).

## Tokens

| Name | Value | Used by |
|------|-------|---------|
| Invitation token length | 64 random characters (stored as SHA-256 hash) | FR-024, FR-027 |

## Lists and general rate limit

| Name | Value | Used by |
|------|-------|---------|
| Page size for company list, company user list, and invitation lists | 15 (Laravel `paginate()` default) | FR-019, FR-028 |
| General API requests per user (or per source when signed out) | 60 per minute | Constitution Security Requirements |

## Roles

| Code | Meaning | Assignable in UI |
|------|---------|------------------|
| `super_admin` | Service owner | No (console command only) |
| `company_admin` | Manages own company | Yes |
| `viewer` | Read-only on own company's events, timesheets, reports | Yes |
