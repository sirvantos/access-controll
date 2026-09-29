# Open Questions: 003-data-isolation-between-companies

## Q1: What may the super admin do with a selected company's data?

**Context**: The feature description says: "The super admin sees all companies but works with company data only after explicitly selecting a company." Feature 001 (FR-020 and Clarification 2026-09-27, option A) and feature 002 (FR-020) say the super admin MUST NOT read or change any company's users, employees, photos, terminals, settings, events, timesheets, or reports. Spec: User Story 6 scenario 7, FR-022.

**What we need to know**: After selecting a company, what may the super admin do with that company's data?

| Option | Answer | Implications |
|--------|--------|--------------|
| A | Read only: see all of the selected company's data (including photos, events, reports, exports) but change nothing | Replaces the read ban in 001 FR-020 / 002 FR-020; the change ban stays |
| B | Read and change like a company admin of the selected company | Replaces 001 FR-020 / 002 FR-020 completely; the super admin acts as a company admin inside the selected company |
| C | Nothing new: 001 FR-020 stays. Selection only scopes the company-level actions already allowed (company record, first-admin invitation) | "Works with company data" is read narrowly; no super admin access to employees, photos, events, or reports |
| Custom | Provide your own answer | For example, read access to some kinds of data but not photos |


Answer: B

## Q2: Which super admin actions are recorded, and who can see the records?

**Context**: The feature description says: "the super admin's actions are logged." It does not say which actions or who reads the records. Feature 001 lists "a user-facing audit log viewer" as out of scope. Spec: User Story 6 scenario 6, FR-020.

**What we need to know**: Which super admin actions on a selected company are recorded, and who can see those records?

| Option | Answer | Implications |
|--------|--------|--------------|
| A | Every action, including viewing and exporting data (for example, opening a photo or downloading a report); kept for review, no viewing screen in this feature | Full trace of biometric data access; no new UI |
| B | Only actions that change data, plus selecting a company; no viewing screen in this feature | Smaller record; viewing photos or reports is not traced |
| C | Every action, including viewing; the selected company's company admins can see the records about their company | Transparency for customers; adds a viewing screen to this feature |
| Custom | Provide your own answer | For example, other super admins can see the records |

Answer: B

## Q3: May a super admin change the data of a selected company that is deactivated?

**Context**: The Q1 answer (B) lets a super admin who has selected a company read and change its data like a company admin. Feature 002 FR-009 says deactivating a company MUST keep all of its data (users, employees, terminals, events, reports, details, settings) unchanged, and reactivating it MUST restore exactly that data. The company admins of a deactivated company cannot sign in (feature 001 FR-003), so "like a company admin" does not settle this case. Spec: FR-022, Assumptions (selecting deactivated companies).

**What we need to know**: When the selected company is deactivated, what may the super admin do with its data?

| Option | Answer | Implications |
|--------|--------|--------------|
| A | Read only: the super admin can select a deactivated company and see its data, but every change is refused until the company is reactivated | Keeps feature 002 FR-009 as written; adds a "company is deactivated" refusal for super admin changes |
| B | Read and change, the same as for an active company | Feature 002 FR-009 must be narrowed: deactivation itself changes nothing, but a super admin may change the data afterwards |
| C | Nothing: a deactivated company cannot be selected; the super admin must reactivate it first | Deactivated companies' data is unreachable until reactivation; the company list still shows them |
| Custom | Provide your own answer | For example, read and change users only, so access can be restored before reactivation |

Answer: B
