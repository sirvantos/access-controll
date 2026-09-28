# Constraints: Company Management

**Feature**: [spec.md](./spec.md)

This file is the source of truth for the boundary values that `spec.md` references, and for any later plan, contract, validation rule, or test of this feature. Values carried over from feature 001 are marked. Other values are common defaults chosen because the description did not set them (see the spec's Assumptions). Change a value here first, then update every artifact that depends on it.

Lengths are counted in characters as the user sees them, not in bytes. Leading and trailing spaces are ignored in every text field.

## Company fields

| Name | Rule | Used by |
|------|------|---------|
| Company name | required, 1 to 255 characters (from feature 001) | FR-001, FR-003, FR-010 |
| Time zone | required; a recognised IANA time zone identifier (see below) | FR-002, FR-010, FR-011 |
| Default time zone | `Asia/Almaty` (from the description) | FR-002 |
| BIN | optional; exactly 12 digits | FR-001, FR-003, FR-006, FR-010 |
| Contact person | optional; 1 to 255 characters | FR-001, FR-003, FR-010 |
| Phone | optional; 10 to 15 digits, optionally starting with `+`, and may contain spaces, hyphens, and parentheses; 32 characters at most | FR-001, FR-003, FR-010 |
| Email | optional; a valid email address, 255 characters at most (same limit as feature 001) | FR-001, FR-003, FR-010 |

## Working day settings

| Name | Rule | Used by |
|------|------|---------|
| Start time, end time | time of day `HH:MM` (00:00 to 23:59); end is later than start | FR-013, FR-015 |
| Working days of the week | a set of the identifiers below; at least one | FR-013, FR-015 |
| Break duration | whole minutes, from 0 to less than the working day length (end minus start) | FR-013, FR-015 |
| Break deducted | yes or no | FR-013 |
| Lateness grace period | whole minutes, from 0 to less than the working day length (end minus start) | FR-013, FR-015 |

## Defaults

| Setting | Default | Used by |
|---------|---------|---------|
| Start time | 09:00 (from the description) | FR-014 |
| End time | 18:00 (from the description) | FR-014 |
| Working days | Monday to Friday (from the description) | FR-014 |
| Break duration | 60 minutes (from the description) | FR-014 |
| Break deducted | yes (answer to Q3 in `.factory/questions.md`) | FR-014 |
| Lateness grace period | 0 minutes (answer to Q3 in `.factory/questions.md`) | FR-014 |

## Company list

| Name | Value | Used by |
|------|-------|---------|
| Page size | 15 (from feature 001) | FR-005 |
| Search text | 255 characters at most; matches part of the name or part of the BIN, ignoring letter case | FR-006 |

## Recognised time zones

The recognised set is every identifier in the IANA time zone database (for example `Asia/Almaty`). An identifier that is not in that set is unknown (FR-002, US1-4).

## Working day identifiers

| Identifier | Day | Used by |
|------------|-----|---------|
| `monday` | Monday | FR-013, FR-014, FR-015 |
| `tuesday` | Tuesday | FR-013, FR-014, FR-015 |
| `wednesday` | Wednesday | FR-013, FR-014, FR-015 |
| `thursday` | Thursday | FR-013, FR-014, FR-015 |
| `friday` | Friday | FR-013, FR-014, FR-015 |
| `saturday` | Saturday | FR-013, FR-014, FR-015 |
| `sunday` | Sunday | FR-013, FR-014, FR-015 |

The default working days are `monday` through `friday`.
