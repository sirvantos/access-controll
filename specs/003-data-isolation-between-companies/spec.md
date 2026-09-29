# Feature Specification: Data Isolation Between Companies

**Feature Branch**: `factory/specify`

**Created**: 2026-09-29

**Status**: Draft

**Input**: User description: "Data isolation between companies in a CRM service for access control and time tracking. The service hosts multiple companies in one system and stores their personal data: employee face photos, entry/exit history, and working hours. One company's data must never be visible or accessible to users of another company. Every record (users, employees, photos, terminals, events, settings, reports, exports) belongs to exactly one company. A user sees and modifies only their own company's data in every part of the service: lists, search, counters, reports, and export files. Attempting to open another company's record by direct link or ID must look like "not found". New records are automatically assigned to the user's company. Photos and event snapshots are not accessible without authentication or through public links. The super admin sees all companies but works with company data only after explicitly selecting a company. The selected company is always shown in the UI, and the super admin's actions are logged. Events from a terminal belong to the company that owns the terminal. Employee numbers are unique within a company, so identical numbers in different companies must never be mixed up. Isolation must work by default, including for all future parts of the service, rather than depending on care taken when building each page."

## Clarifications

### Session 2026-09-29

- Q: After selecting a company, what may the super admin do with that company's data? → A: Read and change it like a company admin of the selected company (option B). This replaces feature 001 FR-020 and feature 002 FR-020 for the selected company.
- Q: Which super admin actions are recorded, and who can see the records? → A: Only actions that change a selected company's data, plus selecting a company (option B). Viewing and exporting are not recorded. No screen for viewing the records is part of this feature.
- Q: May a super admin change the data of a selected company that is deactivated? → A: Yes, read and change it the same as for an active company (option B). This narrows feature 002 FR-009: deactivation itself changes nothing, but a super admin may change the data afterwards.

## User Scenarios & Testing *(mandatory)*

This feature builds on `specs/001-user-authentication-and-roles` (sign-in, the three roles, what each role may do inside its own company) and `specs/002-company-management-for-a` (companies, their state, details, and working day settings). Those features state that users never reach another company's data. This feature defines what "isolated" means across every part of the service, how records get their company, how photos and snapshots are protected, how terminal events and employee numbers are kept apart, and how the super admin works with a selected company.

### User Story 1 - Company users see only their own company's data everywhere (Priority: P1)

A company admin or viewer works in the service. Every list, search result, counter, report, and export file they see contains only records of their own company. Records of other companies never appear, are never counted, and never influence totals.

**Why this priority**: The service stores biometric and personal data of many companies in one system. Any leak between companies is a privacy breach; this is the core guarantee of the service.

**Independent Test**: Seed two companies with overlapping data (employees with the same names and employee numbers, terminals, events on the same days, reports). As a user of company A, open every list, run searches that would match company B's records, read every counter, build every report, and download every export; confirm that only company A's records appear and that every count and total equals company A's own data.

**Acceptance Scenarios**:

1. **Given** companies A and B each with employees, **When** a user of company A opens any list (users, employees, photos, terminals, events, reports, exports), **Then** only company A's records are shown.
2. **Given** an employee named "Aigerim Sarsenova" in company B and none in company A, **When** a user of company A searches for "Sarsenova", **Then** the result is empty.
3. **Given** company A with 10 employees and company B with 25, **When** a user of company A views any counter or total (for example, number of employees, number of events today), **Then** it reflects only company A's data (10 employees).
4. **Given** events of both companies on the same day, **When** a user of company A builds a report or timesheet for that day, **Then** it contains only company A's events and hours.
5. **Given** a user of company A, **When** they generate any export file, **Then** the file contains only company A's records.
6. **Given** a user of company A, **When** they filter, sort, or page through any list, **Then** no page, filter value, or suggestion reveals a company B record.

---

### User Story 2 - Another company's record looks like it does not exist (Priority: P1)

A user of company A obtains a direct link or identifier of a record that belongs to company B (an employee, a photo, an event, a terminal, a report, an export file, a user) and tries to open, change, or delete it. The service answers exactly as it would for a record that does not exist.

**Why this priority**: Identifiers leak through shared links, browser history, and guessing. If the response differed from "not found", it would confirm that the record exists in some other company.

**Independent Test**: For each kind of record, take an identifier from company B and an identifier that exists nowhere. As a user of company A, request to open, change, and delete both; confirm the two responses are identical and nothing in company B changes.

**Acceptance Scenarios**:

1. **Given** an employee record of company B, **When** a user of company A opens it by direct link or identifier, **Then** the service shows the same "not found" result it shows for an identifier that exists nowhere.
2. **Given** a record of company B, **When** a user of company A tries to change or delete it, **Then** the service answers "not found" and the record is unchanged.
3. **Given** a record of company B, **When** a user of company A refers to it from their own data (for example, links an employee of company A to a terminal of company B, or builds a report filtered by a company B employee), **Then** the reference is refused as if the referenced record did not exist, and nothing is saved.
4. **Given** a record of company B, **When** a user of company A requests it, **Then** the response contains no part of the record (no name, photo, time, or count) and does not differ in content from the response for a non-existent record.

---

### User Story 3 - New records belong to the creator's company automatically (Priority: P1)

A company admin creates a record (an employee, a photo, a terminal, a setting, a report, an export, an invitation). The record is assigned to the admin's own company without the admin choosing a company, and it cannot be assigned to or moved into another company.

**Why this priority**: If the company of a new record depended on what the user submits, a user could plant data in another company or create records that belong to no company.

**Independent Test**: As a company admin of company A, create one record of each kind; confirm each belongs to company A. Repeat while submitting company B's identifier as the owning company; confirm the record still belongs to company A (or is refused) and company B is unchanged.

**Acceptance Scenarios**:

1. **Given** a company admin of company A, **When** they create any record, **Then** the record belongs to company A.
2. **Given** a company admin of company A, **When** they submit a new record naming company B as its company, **Then** the record is not created in company B and company B is unchanged.
3. **Given** a company admin of company A, **When** they edit one of their records and try to change its company, **Then** the record stays in company A.
4. **Given** any function of the service, **When** it would save a record that belongs to no company, **Then** the save is refused.

---

### User Story 4 - Photos and event snapshots are never publicly reachable (Priority: P1)

Employee face photos and event snapshots are biometric data. They are shown only to signed-in users of the owning company whose role allows seeing them. A copied image link does not work for anyone who is not signed in, for users of other companies, or after the user signs out.

**Why this priority**: Images are the most sensitive data in the service and the most likely to be shared by copying a link.

**Independent Test**: As a company admin of company A, open an employee photo and an event snapshot and copy their addresses. Open the addresses without signing in, as a user of company B, and after signing out; confirm the image is not returned in every case.

**Acceptance Scenarios**:

1. **Given** an employee photo or an event snapshot of company A, **When** anyone who is not signed in requests it, **Then** the image is not returned.
2. **Given** an employee photo or an event snapshot of company A, **When** a signed-in user of company B requests it, **Then** the service answers "not found" as in User Story 2.
3. **Given** an image address copied by a user of company A, **When** it is opened after that user signs out, or shared with another person, **Then** the image is not returned without a signed-in session that is allowed to see it.
4. **Given** any photo or snapshot, **When** the service shows it, **Then** it is never delivered through a public, permanent, or guessable address.
5. **Given** a signed-in user of company A whose role does not allow seeing a kind of image (as defined by feature 001), **When** they request it, **Then** it is refused.

---

### User Story 5 - Terminal events and employee numbers never cross companies (Priority: P1)

A face recognition terminal sends entry/exit events. Each event belongs to the company that owns the terminal. Two companies may use the same employee number for different people; an event or a record for employee number 17 in company A never affects employee number 17 in company B.

**Why this priority**: Events carry an employee number from the terminal. If events were matched to employees across the whole system, one company's hours would be credited to another company's employee.

**Independent Test**: Create employee number 17 in company A and in company B. Send an event for employee 17 from a terminal of company A; confirm it appears only in company A for company A's employee 17 and that company B's employee 17 has no new event.

**Acceptance Scenarios**:

1. **Given** a terminal owned by company A, **When** it sends an event, **Then** the event belongs to company A.
2. **Given** employee number 17 in company A and in company B, **When** a company A terminal sends an event for employee number 17, **Then** it is recorded only for company A's employee 17, and company B's employee 17 is unaffected.
3. **Given** a terminal owned by company A, **When** it sends an event for an employee number that exists only in company B, **Then** the event is never attached to company B's employee.
4. **Given** a company admin of company A, **When** they create or edit an employee with a number already used by another employee of company A, **Then** the save is refused with an explanation.
5. **Given** a company admin of company A, **When** they create an employee with a number already used in company B, **Then** the employee is created, and nothing reveals that the number exists in company B.

---

### User Story 6 - Super admin works with company data only inside a selected company (Priority: P2)

A super admin sees the list of all companies. To work with a company's data they must first explicitly select that company. While a company is selected, the super admin reads and changes its data as a company admin of that company would, its name is always visible in the interface, every list, search, counter, report, and export shows only that company's data, and every selection and every change the super admin makes is recorded. Without a selected company the super admin reaches no company's data.

**Why this priority**: The super admin is the only user who can see more than one company, so their work must be scoped just as strictly, visibly, and traceably. It follows the P1 stories because company users are the majority and must be protected first.

**Independent Test**: As a super admin, try to open company data without selecting a company; confirm it is refused. Select company A; confirm the interface shows "Company A" on every screen, only company A's data appears, and a record of company B opened by identifier answers "not found". Change a record of company A and confirm the change and the earlier selection of company A are both recorded with the super admin, the time, the selected company, and the action; open a photo and download an export and confirm no record is added for them.

**Acceptance Scenarios**:

1. **Given** a signed-in super admin with no company selected, **When** they request any company's data, **Then** the request is refused and they are asked to select a company.
2. **Given** a signed-in super admin, **When** they select company A, **Then** the name of company A is shown on every screen until they change or clear the selection.
3. **Given** a super admin with company A selected, **When** they open any list, search, counter, report, or export, **Then** only company A's data is included, exactly as for a user of company A.
4. **Given** a super admin with company A selected, **When** they open a record of company B by direct link or identifier, **Then** the service answers "not found".
5. **Given** a super admin with company A selected, **When** they switch to company B, **Then** from that moment only company B's data is shown and the interface shows company B.
6. **Given** a signed-in super admin, **When** they select a company, or change company A's data while company A is selected, **Then** a record is kept of who acted, when, in which company, and what was done.
7. **Given** a super admin with company A selected, **When** they only view or export company A's data (for example, open a photo or download a report), **Then** no action record is required.
8. **Given** a super admin with company A selected, **When** they read or change company A's data, **Then** it is allowed exactly when a company admin of company A would be allowed to do the same, and refused with the same explanation when a company admin would be refused (for example, removing the last active admin, feature 001 FR-037).

---

### User Story 7 - Isolation applies by default to every current and future part of the service (Priority: P2)

When a new part of the service is added (a new list, report, export, or kind of record), its data is isolated between companies without the builders of that part having to add anything for it. A part that forgot to handle companies shows no other company's data.

**Why this priority**: Isolation that depends on care in every screen will eventually fail. It is P2 because it protects future work; the P1 stories protect what exists today.

**Independent Test**: Run an automated check that walks every function of the service that returns or changes company data, as a user of company A, against seeded data of companies A and B; confirm that no function returns, counts, or changes company B data. Add a new kind of company record in a test without any isolation-specific work and confirm the same check passes for it.

**Acceptance Scenarios**:

1. **Given** any function of the service that reads company data, **When** it is used by a user of company A, **Then** it returns only company A's data even if that function contains no company-specific logic of its own.
2. **Given** a new kind of company record added to the service, **When** it is created, read, listed, counted, or exported, **Then** the rules of User Stories 1–3 apply to it without extra work for that record.
3. **Given** a function that is expected to work across all companies (for example, the super admin company list from feature 002), **When** it is built, **Then** it is an explicit, named exception rather than the default behaviour.

---

### Edge Cases

- A user's company is deactivated while they are signed in: they lose access on their next request (feature 001 FR-006); no data of that company is returned after that.
- A user's role or company membership changes while an export is being prepared: the export contains only data the user may see at the moment it is generated, and a file prepared for one company is never delivered to a user of another.
- An export file or report link of company A is shared with a user of company B or with someone not signed in: the file is not returned (it looks like "not found" to company B).
- A terminal sends an event with an employee number that does not exist in the terminal's company: the event is never attached to an employee of another company.
- A terminal that the service does not recognise as owned by any company sends an event: the event is not added to any company.
- Two companies use the same employee number, the same employee name, or the same terminal name: each record stays within its own company in every list, search, report, and event.
- Search text, filters, or sort order that would match only another company's records: the result is empty, and no suggestion, autocomplete value, or total reveals those records.
- A user of company A submits a record that refers to another record of company B by identifier (for example, an event correction pointing at a company B employee): the reference is treated as not found and nothing is saved.
- A super admin opens the service in two browser tabs and selects different companies: every screen shows the company its data belongs to, and no screen mixes data from two companies.
- A super admin selects a deactivated company and changes its data: the change is allowed as for an active company and is recorded (FR-020, FR-022); the company stays deactivated and its users still cannot sign in.
- A super admin has no company selected and follows a direct link to a company record: they are asked to select a company and see no data until they do.
- Background work (report building, exports, event processing) that runs without a signed-in user: it works only on the one company it was started for.

## Requirements *(mandatory)*

### Functional Requirements

**Ownership**

- **FR-001**: Every company record — users (except super admins, per feature 001 FR-015), invitations, employees, employee numbers, photos, event snapshots, terminals, events, settings, timesheets, reports, and export files — MUST belong to exactly one company.
- **FR-002**: A new record MUST be assigned to the company of the user who creates it (or to the selected company for a super admin, FR-022), regardless of any company named in the submitted data.
- **FR-003**: The service MUST refuse to save a company record that belongs to no company, and MUST NOT allow any user to move an existing record to another company.

**Visibility and access for company users**

- **FR-004**: A company admin or viewer MUST see and change only records of their own company, within the limits of their role (feature 001 FR-016, FR-017).
- **FR-005**: Every list, search, filter, sort, page, suggestion, counter, total, report, timesheet, and export file MUST include only records of the user's own company.
- **FR-006**: A request by a company admin or viewer to open, change, or delete a record of another company MUST produce the same response as a request for a record that does not exist, and MUST NOT reveal any part of that record.
- **FR-007**: A submitted reference from one company's data to another company's record MUST be treated as a reference to a non-existent record, and nothing MUST be saved.

**Photos, snapshots, and files**

- **FR-008**: Employee photos and event snapshots MUST be returned only to a signed-in user of the owning company whose role allows seeing them, and to a super admin only while the owning company is selected (FR-022).
- **FR-009**: Photos, event snapshots, reports, and export files MUST NOT be reachable through a public, permanent, or guessable address, or without a signed-in session that is allowed to see them; a copied address MUST NOT work for anyone else or after the session ends.
- **FR-010**: An export file MUST be delivered only to users allowed to see the data of the company it was generated for.

**Terminals, events, and employee numbers**

- **FR-011**: Every event received from a terminal MUST belong to the company that owns that terminal.
- **FR-012**: An event MUST be matched only to an employee of the company that owns the sending terminal; it MUST NOT be attached to an employee of any other company, even one with the same employee number.
- **FR-013**: An event from a terminal that is not owned by any company MUST NOT be added to any company.
- **FR-014**: An employee number MUST be unique within its company; the same number MAY be used in different companies, and a conflict check MUST NOT reveal that a number is used in another company.

**Super admin**

- **FR-015**: A super admin MUST be able to see the list of all companies (feature 002 FR-005) without selecting a company.
- **FR-016**: A super admin MUST NOT reach any company's data until they explicitly select one company.
- **FR-017**: While a company is selected, the service MUST show the name of the selected company on every screen.
- **FR-018**: While a company is selected, every list, search, counter, report, and export seen by the super admin MUST include only that company's data, and records of other companies MUST answer as not found (FR-006).
- **FR-019**: A super admin MUST be able to change the selected company or clear the selection; after the change, only the newly selected company's data MUST be reachable.
- **FR-020**: The service MUST record every selection of a company by a super admin (including switching to another company) and every super admin action that changes a selected company's data, with at least the super admin, the time, the selected company, and the action performed. Viewing and exporting data are not required to be recorded. A screen for viewing these records is not part of this feature.
- **FR-021**: The recorded super admin actions MUST NOT be changeable or removable by the super admin or by company users.
- **FR-022**: While a company is selected, a super admin MUST be able to read and change that company's data exactly as a company admin of that company may (feature 001 FR-016 and the rules that limit company admins, such as FR-037), and nothing beyond that. For the selected company this replaces feature 001 FR-020 and feature 002 FR-020; without a selected company FR-016 applies. The same applies when the selected company is deactivated; this narrows feature 002 FR-009 so that deactivation itself changes no data, while a super admin may still change a deactivated company's data.

**Isolation by default**

- **FR-023**: Company isolation MUST apply to every function that reads or changes company data by default, so that a function built without any company-specific handling still returns and changes only the current company's data.
- **FR-024**: Any function that deliberately works across companies MUST be an explicit, named exception whose identifier is one of the rows in [contracts/isolation.md](./contracts/isolation.md). That list is the only legal `withoutIsolation` set; it includes feature 001/002 super-admin company functions, feature 001 global email and sign-in lookups, and terminal load before company context exists.
- **FR-025**: Work that runs without a signed-in user (event processing, report and export generation) MUST act on exactly one company and MUST NOT read or write another company's data.
- **FR-026**: The service MUST have an automated check that exercises every function that returns or changes company data with two companies and fails if any function returns, counts, or changes another company's data.

### Key Entities

- **Company**: The owner of all company records (feature 002). Every record in FR-001 belongs to exactly one company; a record never changes company.
- **Company record**: Any user, invitation, employee, photo, event snapshot, terminal, event, setting, timesheet, report, or export file. Belongs to exactly one company and is visible only within it.
- **Employee number**: The identifier a terminal uses to recognise an employee. Unique within one company; the same value may exist in other companies for different people.
- **Terminal**: A face recognition device owned by one company. Every event it sends belongs to that company.
- **Event and event snapshot**: An entry/exit record from a terminal and the image captured with it. Belongs to the terminal's company.
- **Selected company**: The single company a super admin has chosen to work in. Scopes everything the super admin sees and does with company data until changed or cleared.
- **Super admin action record**: A record of a company selection by a super admin or of a change a super admin made to a selected company's data: who, when, which company, what action. Cannot be changed or removed.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In an automated check with two seeded companies, 0 records, counts, or totals of company B appear in any list, search, counter, report, timesheet, or export seen by a user of company A, across 100% of functions that return company data.
- **SC-002**: For 100% of record kinds, a request by a user of company A for a company B record is indistinguishable from a request for a record that does not exist.
- **SC-003**: 100% of newly created records belong to the creator's company, including when a different company is named in the submitted data.
- **SC-004**: 100% of requests for photos, event snapshots, reports, or export files without an allowed signed-in session are refused.
- **SC-005**: 100% of terminal events are recorded in the company that owns the terminal, and 0 events are attached to an employee of another company with the same employee number.
- **SC-006**: 100% of super admin requests for company data without a selected company are refused, and the selected company is visible on 100% of screens while one is selected.
- **SC-007**: 100% of company selections by a super admin and 100% of super admin changes to a selected company's data produce a record with the super admin, time, company, and action.
- **SC-008**: A new kind of company record added without any isolation-specific work passes the same two-company check as existing records on the first run.

## Assumptions

- Features 001 and 002 are in place and are reused: roles and their permissions, sign-in, session ending on deactivation, the super admin company list, and company deactivation.
- The super admin belongs to no company (feature 001 FR-015); "every user belongs to exactly one company" in the description applies to company admins and viewers.
- "Look like not found" means the same answer and the same content as for a record that exists nowhere, consistent with feature 001 FR-018.
- An event belongs to the company that owns the terminal at the moment the event is received. Moving a terminal between companies is not described and is not part of this feature.
- Export files and reports generated by one user may be opened by other users of the same company whose role allows seeing that data; the description ties them to the company, not to the person.
- Selecting a company is available for deactivated companies too, because the super admin sees all companies and a deactivated company's data is kept (feature 002 FR-009); the super admin may read and change that data as for an active company (FR-022).
- Company isolation adds no new role or permission; it narrows every existing permission to one company.
- Out of scope: users who belong to several companies, sharing data between companies, moving records between companies, and branches within a company (feature 002).
