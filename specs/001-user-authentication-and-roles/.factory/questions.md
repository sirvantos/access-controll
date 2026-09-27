# Open Questions: 001-user-authentication-and-roles

## Q1: What may a super admin do inside a company? (spec FR-020)

**Context**: The description says the super admin "manages all companies and creates the first administrator for each company", and that the data is personal and biometric, accessible "only within the limits of their role". It does not say whether "manages all companies" includes the company's users or its employee/biometric data.

**What we need to know**: Beyond creating companies, listing them, deactivating/reactivating them, and inviting the first company admin, what may a super admin do inside a company?

| Option | Answer | Implications |
|--------|--------|--------------|
| A | Nothing more | Least access to biometric data. If a company loses all admins' credentials, only password recovery helps; the super admin cannot invite a replacement once the first admin has accepted. |
| B | Also manage the company's users (invite, re-send/revoke invitations, deactivate/reactivate, change role), but no access to employees, photos, events, timesheets, or reports | Super admin can recover a company that lost its admins, without seeing biometric data. |
| C | Full access to all of the company's data, like a company admin of every company | Simplest support model; the service owner can see every company's personal and biometric data. |
| Custom | Describe the exact permissions | — |

**Answer**: _pending_
