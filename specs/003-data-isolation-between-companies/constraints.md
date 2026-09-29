# Constraints: Data Isolation Between Companies

**Feature**: [spec.md](./spec.md)

Source of truth for the boundary values this feature introduces, and for values reused from features 001 and 002. Change a value here first, then update every dependent artifact.

Lengths are counted in characters as the user sees them, not in bytes.

## Company context

| Name | Value | Used by |
|------|-------|---------|
| Super admin company-context header | `X-Company-Context` | FR-016, FR-018, FR-019 |
| Header value | the selected company's integer id | FR-016 |

A company admin or viewer MUST NOT change company by sending this header (FR-002, FR-004). Their context is always `Actor::actorCompanyId()`.

## Employee number (FR-014)

| Name | Value | Used by |
|------|-------|---------|
| Employee number | required; 1 to 32 characters; unique within one company | FR-014, US5 |

The spec uses values such as `17`. The identifier is stored as a string so the same characters can exist in two companies without being mixed up. A uniqueness refusal MUST NOT reveal that another company uses the number.

## Isolation proof records

These limits apply to the company-owned proof records this feature persists so FR-011–FR-014 and media rules can be tested before later product screens exist. They are storage limits, not a product UI.

| Name | Value | Used by |
|------|-------|---------|
| Employee name | 1 to 255 characters | US1 search scenario |
| Terminal name | 1 to 255 characters | US5 |
| Media public id | UUID (36 characters) | FR-008, FR-009 |

## Lists and rate limit (from feature 001)

| Name | Value | Used by |
|------|-------|---------|
| Page size | 15 | company-data lists |
| General API requests | 60 per minute | Constitution Security Requirements |

## Reused from features 001 and 002

Company name (1–255 characters), roles, and the 404 envelope for another company's record stay as in those features. This feature does not change those numbers.
