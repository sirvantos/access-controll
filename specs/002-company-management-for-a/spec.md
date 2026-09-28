# Feature Specification: Company Management

**Feature Branch**: `factory/specify`

**Created**: 2026-09-28

**Status**: Draft

**Input**: User description: "Company management for a CRM service for access control and time tracking. The service serves multiple client companies. A company is the core unit of the service: it owns users, employees, face recognition terminals, entry/exit events, and reports. The super admin creates companies, sees a searchable list of them, and can deactivate or reactivate a company. Companies are never deleted. Their data is kept when they are deactivated. A company has a name, a time zone (default Asia/Almaty), and optional details: business ID (BIN), contact person, phone, email. The time zone is needed to display entry/exit times correctly and to assign each event to the correct day. A company has working day settings used to calculate late arrivals and hours worked: start and end of the working day, working days of the week, break duration and whether it is deducted, and a lateness grace period in minutes. Defaults are 09:00–18:00, Monday–Friday, 60-minute break. Changing the settings must not change calculations for past days. A company admin can edit the details and settings of their own company only. A viewer sees them read-only. Out of scope for now: night shifts, individual employee schedules, holiday calendar, branches within a company, pricing plans and billing."

## Clarifications

### Session 2026-09-28

- Q: From which moment does a saved change to the working day settings apply to late-arrival and hours-worked calculations? → A: From the next day in the company's time zone; today and every earlier day keep the old settings.
- Q: When a company admin changes the time zone, what happens to days already recorded? → A: Past days keep the time zone that applied then; the new time zone applies from the same moment as a settings change (the next day), and the service keeps a time zone history like the settings history.
- Q: What are the defaults for break deduction and the lateness grace period? → A: Break deducted; grace period 0 minutes.

## User Scenarios & Testing *(mandatory)*

This feature builds on `specs/001-user-authentication-and-roles`. That feature already defines sign-in, the three roles, tenant isolation, the first-admin invitation sent when a company is created, and the effect of company deactivation on users' sessions. This spec adds the company's own data (details, time zone, working day settings), the searchable company list, and who may read or change that data.

### User Story 1 - Super admin creates a company with its details (Priority: P1)

A super admin creates a new client company. They enter the company name, the time zone (pre-filled with Asia/Almaty), the first admin's email (as defined by feature 001), and optionally the BIN, contact person, phone, and email. The company is created active, with the default working day settings.

**Why this priority**: A company is the core unit of the service; nothing else (users, employees, terminals, events, reports) can exist without one.

**Independent Test**: As a super admin, create a company entering only a name and the first admin's email; confirm it is active, its time zone is Asia/Almaty, and its working day settings are the defaults. Create a second company with every optional detail filled in and a different time zone; confirm all values are saved.

**Acceptance Scenarios**:

1. **Given** a signed-in super admin, **When** they create a company with a name and the first admin's email and leave the time zone unchanged, **Then** the company is created active with time zone Asia/Almaty.
2. **Given** a signed-in super admin, **When** they create a company and choose another time zone and fill in BIN, contact person, phone, and email, **Then** all entered values are saved with the company.
3. **Given** a newly created company, **When** its working day settings are read, **Then** they are: start 09:00, end 18:00, working days Monday to Friday, break 60 minutes, break deducted, and lateness grace period 0 minutes.
4. **Given** a signed-in super admin, **When** they submit a company without a name, with an unknown time zone, or with an optional detail in the wrong format (see `constraints.md`), **Then** the company is not created and each invalid field is explained.
5. **Given** a signed-in company admin or viewer, **When** they try to create a company, **Then** the action is refused.

---

### User Story 2 - Super admin finds companies in a searchable list (Priority: P1)

A super admin opens the list of all companies. Each row shows the company name, BIN, whether it is active or deactivated, and when it was created. The super admin types part of a name or BIN to narrow the list.

**Why this priority**: The super admin serves many client companies and must find a specific one to deactivate or reactivate it. Without the list, created companies cannot be managed.

**Independent Test**: Seed three companies (two active, one deactivated) with known names and BINs. As a super admin, open the list and confirm all three appear with their state; search by part of a name and by part of a BIN and confirm only the matching companies are shown.

**Acceptance Scenarios**:

1. **Given** a signed-in super admin and several companies, **When** they open the company list, **Then** every company appears, active and deactivated, with its name, BIN (if set), state, and creation date.
2. **Given** a company named "Alma Stroy", **When** the super admin searches for "stroy", **Then** that company is shown (search ignores letter case).
3. **Given** a company with BIN "123456789012", **When** the super admin searches for "4567", **Then** that company is shown.
4. **Given** a search that matches no company, **When** it is submitted, **Then** the list is empty and says that nothing was found.
5. **Given** more companies than fit on one page (see `constraints.md`), **When** the super admin opens the list, **Then** the list is split into pages and the search applies across all pages.
6. **Given** a signed-in company admin or viewer, **When** they try to open the list of all companies, **Then** access is refused.

---

### User Story 3 - Super admin deactivates and reactivates a company (Priority: P1)

A super admin deactivates a company that has stopped using the service. The company and all of its data remain stored. Later the super admin can reactivate it, and everything is as before.

**Why this priority**: Customers leave and return; the service must cut off access without losing data, and never delete a company.

**Independent Test**: Create a company, change its details and settings, deactivate it, confirm it shows as deactivated in the list with all details and settings unchanged, reactivate it, and confirm it is active again with the same data.

**Acceptance Scenarios**:

1. **Given** an active company, **When** the super admin deactivates it, **Then** the company is marked deactivated in the list and its users lose access as defined by feature 001.
2. **Given** a deactivated company, **When** the super admin reactivates it, **Then** the company is active again and its name, time zone, details, working day settings, settings history, and time zone history are exactly as they were before deactivation.
3. **Given** a deactivated company, **When** its data is inspected, **Then** its users, employees, terminals, events, reports, details, and settings are all still stored.
4. **Given** a company that is already in the requested state, **When** the super admin deactivates an already deactivated company or reactivates an already active one, **Then** nothing changes and no error is shown.
5. **Given** any signed-in user, **When** they try to delete a company, **Then** no delete option exists and any such request is refused.
6. **Given** a signed-in company admin or viewer, **When** they try to deactivate or reactivate a company, **Then** the action is refused.

---

### User Story 4 - Company admin edits the company's details and time zone (Priority: P2)

A company admin opens their company's profile and corrects the name, time zone, BIN, contact person, phone, or email. Optional details can be cleared.

**Why this priority**: Details entered at creation are often incomplete or wrong. The company is usable with defaults, so this follows the P1 stories.

**Independent Test**: As a company admin of company A, change each detail and clear an optional one; confirm the changes are saved. As a company admin of company A, try to change company B; confirm it is refused without revealing company B.

**Acceptance Scenarios**:

1. **Given** a signed-in company admin, **When** they change their company's name, BIN, contact person, phone, or email to valid values, **Then** the changes are saved and shown on the next view.
2. **Given** a signed-in company admin, **When** they clear the BIN, contact person, phone, or email, **Then** that detail becomes empty.
3. **Given** a signed-in company admin, **When** they clear the name or enter a detail in the wrong format, **Then** nothing is saved and each invalid field is explained.
4. **Given** a signed-in company admin, **When** they change the time zone, **Then** the new time zone is saved and applies from the next day as defined in FR-012; today and every earlier day keep the time zone that applied to them.
5. **Given** a signed-in company admin of company A, **When** they try to read or change the details of company B, **Then** the request is refused and nothing about company B is revealed, including whether it exists.

---

### User Story 5 - Company admin changes working day settings without changing past days (Priority: P2)

A company admin changes the working day settings of their company: start and end time, working days of the week, break duration, whether the break is deducted, and the lateness grace period. The new settings are used for late-arrival and hours-worked calculations from the moment defined in FR-016. Days before that keep the results they were calculated with.

**Why this priority**: Late arrivals and hours worked depend on these settings, and every company's schedule differs from the defaults. It follows the P1 stories because defaults let a company start.

**Independent Test**: Record a completed day under the default settings and note its late-arrival and hours-worked results. Change the start time and break deduction. Confirm the recorded day's results are unchanged, and that a day after the change is calculated with the new settings.

**Acceptance Scenarios**:

1. **Given** a signed-in company admin, **When** they save valid working day settings, **Then** the new settings become the company's current settings.
2. **Given** a company with past days calculated under the previous settings, **When** a company admin changes the settings, **Then** the late-arrival and hours-worked results of those past days stay exactly the same.
3. **Given** a settings change, **When** a day on or after the moment the change applies is calculated, **Then** it uses the new settings.
4. **Given** a signed-in company admin, **When** they enter an end time that is not after the start time, select no working days, or enter a break or grace period outside the allowed range (see `constraints.md`), **Then** nothing is saved and each invalid field is explained.
5. **Given** a signed-in company admin of company A, **When** they try to read or change the working day settings of company B, **Then** the request is refused and nothing about company B is revealed.

---

### User Story 6 - Viewer sees company details and settings read-only (Priority: P3)

A viewer opens their company's profile and working day settings to understand how late arrivals and hours worked are calculated. They see every value but cannot change anything.

**Why this priority**: Viewers read timesheets and reports; seeing the rules behind them is useful but not essential for the first release.

**Independent Test**: As a viewer, open the company profile and settings and confirm all values are shown; attempt to change any value and confirm it is refused.

**Acceptance Scenarios**:

1. **Given** a signed-in viewer, **When** they open their company's details and working day settings, **Then** all values are shown and no edit option is offered.
2. **Given** a signed-in viewer, **When** they try to change any company detail, the time zone, or any working day setting, **Then** the action is refused and nothing changes.
3. **Given** a signed-in viewer of company A, **When** they try to read company B's details or settings, **Then** the request is refused and nothing about company B is revealed.

---

### Edge Cases

- Two company admins of the same company save different settings at nearly the same time: both saves complete, the one saved last becomes current, and no past day changes.
- A company admin saves settings identical to the current ones: nothing changes and no past day changes.
- A company admin changes the settings more than once before the change applies (see FR-016): only the last saved version applies from that moment.
- The time zone is changed while an employee is at work: that day's events are still assigned and displayed in the previous time zone; the new time zone applies from the next day (FR-012).
- A company admin changes the time zone more than once on the same day: only the last saved time zone applies from the next day.
- A company is deactivated and reactivated: its settings history and time zone history are unchanged and past days keep their results.
- An optional detail is submitted as an empty value: it is stored as empty, not as an error.
- The same BIN or name is used by two companies: both are accepted (see Assumptions).
- A super admin searches with leading or trailing spaces: the spaces are ignored.
- A super admin requests the details or working day settings of a company after creation: refused, as defined by feature 001 FR-020 and FR-020 here.

## Requirements *(mandatory)*

### Functional Requirements

**Company creation and list (super admin)**

- **FR-001**: A super admin MUST be able to create a company by entering a name, a time zone, and the first admin's email (feature 001 FR-023), and optionally a BIN, contact person, phone, and email.
- **FR-002**: The time zone MUST default to Asia/Almaty and MUST be one of the recognised time zones listed in `constraints.md`.
- **FR-003**: Every field MUST meet the format and length rules in `constraints.md`; an invalid field MUST block the save and be explained to the user.
- **FR-004**: A new company MUST be active and MUST get the default working day settings defined in FR-014.
- **FR-005**: A super admin MUST be able to view a list of all companies, active and deactivated, showing each company's name, BIN, state, and creation date, split into pages of the size defined in `constraints.md`.
- **FR-006**: A super admin MUST be able to search the list by part of the company name or part of the BIN; the search MUST ignore letter case and surrounding spaces and MUST apply across all pages.

**Deactivation and retention**

- **FR-007**: A super admin MUST be able to deactivate an active company and reactivate a deactivated one; repeating the current state MUST change nothing and MUST not fail.
- **FR-008**: Companies MUST never be deleted by any user or any function of the service.
- **FR-009**: Deactivating a company MUST keep all of its data (users, employees, terminals, events, reports, details, time zone, working day settings, settings history, and time zone history) unchanged; reactivating it MUST restore access to exactly that data.

**Details and time zone (company admin)**

- **FR-010**: A company admin MUST be able to change their own company's name, time zone, BIN, contact person, phone, and email, and to clear any optional detail.
- **FR-011**: The time zone that applies to a day MUST be used to decide which events belong to that day and to display that day's entry/exit times.
- **FR-012**: A saved time zone change MUST apply from the start of the next day in the time zone in effect when the change is saved; today and every earlier day MUST keep the time zone that applied to them, for both event-to-day assignment and displayed times. The service MUST keep every version of a company's time zone together with the day from which it applies.

**Working day settings**

- **FR-013**: Every company MUST have working day settings made of: start time, end time, working days of the week, break duration in minutes, whether the break is deducted from hours worked, and a lateness grace period in minutes.
- **FR-014**: The default settings MUST be: start 09:00, end 18:00, Monday to Friday, break 60 minutes, break deducted, lateness grace period 0 minutes (values in `constraints.md`).
- **FR-015**: The end time MUST be later than the start time on the same day (night shifts are out of scope); at least one working day MUST be selected; break duration and grace period MUST be within the ranges in `constraints.md`.
- **FR-016**: A saved settings change MUST apply to late-arrival and hours-worked calculations from the start of the next day in the company's time zone; today and every earlier day MUST keep the settings that applied to them.
- **FR-017**: The service MUST keep every version of a company's working day settings together with the day from which it applies, so that any past day can be calculated with the settings that applied to it.
- **FR-018**: A company admin MUST be able to change their own company's working day settings.

**Access**

- **FR-019**: A company admin and a viewer MUST be able to read their own company's name, time zone, details, and current working day settings; a viewer MUST NOT be able to change any of them.
- **FR-020**: A super admin MUST NOT be able to read or change a company's working day settings, and MUST NOT be able to change a company's name, time zone, or details after creation (consistent with feature 001 FR-020).
- **FR-021**: A company admin or viewer MUST NOT be able to create, list, deactivate, or reactivate companies, and MUST NOT be able to read or change another company's details or settings; such requests MUST be refused without revealing whether the other company exists.

### Key Entities

- **Company**: A client organisation served by the service. Has a name, a time zone, optional BIN, contact person, phone, and email, a state (active or deactivated), and a creation date. Owns its users, employees, terminals, events, reports, and working day settings. Never deleted.
- **Working day settings version**: One set of working day rules of a company (start time, end time, working days of the week, break duration, break deduction, lateness grace period) and the day from which it applies. A company has one or more versions; the latest one that applies to a day is used for that day.
- **Time zone version**: One recognised region time zone of a company (for example Asia/Almaty) and the day from which it applies. It defines the company's local time and day boundaries for the days it covers. A company has one or more versions; the latest one that applies to a day is used for that day.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A super admin can create a company with only the required fields in under 1 minute.
- **SC-002**: A super admin can find a specific company by part of its name or BIN among 1,000 companies in under 10 seconds.
- **SC-003**: After any number of settings changes, 100% of days before the change applies keep exactly their previous late-arrival and hours-worked results.
- **SC-004**: 100% of attempts by a viewer to change company details or settings are refused.
- **SC-005**: 100% of attempts by a company admin or viewer to read or change another company's details or settings are refused, and none reveals whether that company exists.
- **SC-006**: 0 companies are ever deleted; after deactivation and reactivation, 100% of a company's details, settings, settings history, and time zone history are unchanged.
- **SC-007**: 100% of entry/exit times of a company are displayed in the company time zone that applied to the event's day, and a time zone change alters no earlier day's event assignment or displayed times.

## Assumptions

- Feature 001 is in place: roles, tenant isolation, the first-admin invitation on company creation, and ending users' sessions when a company is deactivated are reused, not redefined.
- The super admin enters the time zone and the optional details when creating a company, because they create it and the description lists these as company attributes. After creation only the company admin changes them (FR-020), which keeps the super admin's minimal access agreed in feature 001.
- "Details" that a company admin may edit include the name and the time zone as well as the optional details, because otherwise no one could correct a company's name after creation.
- Feature 001 FR-017 forbids a viewer from opening settings management. That rule covers changing settings; this feature's explicit statement that a viewer "sees them read-only" allows a viewer to read the company's details and settings without any edit option.
- The company list shows the BIN so the super admin can tell companies with similar names apart, and search covers name and BIN, the two identifying fields. Other details are not searched.
- Neither the company name nor the BIN has to be unique; the description does not require it.
- The BIN, when entered, is a 12-digit Kazakhstan business identification number, consistent with the default time zone Asia/Almaty. Formats and lengths are in `constraints.md`.
- The lateness grace period is the number of minutes after the start time within which an arrival is not counted as late.
- The same working day settings apply to every working day of the week; per-day schedules are not described and are out of scope along with individual employee schedules.
- Late-arrival and hours-worked calculations themselves belong to the timesheet feature; this feature supplies the settings, their history, and the time zone those calculations use.
- Recording who changed company details or settings, and a change history screen, are not described and are not part of this feature.
- Out of scope: night shifts, individual employee schedules, holiday calendar, branches within a company, pricing plans and billing.
