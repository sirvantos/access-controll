# Feature Specification: User Authentication and Roles

**Feature Branch**: `001-user-authentication-and-roles`

**Created**: 2026-09-27

**Status**: Draft

**Input**: User description: "User authentication and roles for a CRM service for access control and time tracking. The service serves multiple companies. It stores employees with face photos, receives entry/exit events from face recognition terminals, and calculates hours worked. This is personal and biometric data, so only authenticated users may access it, and only within the limits of their role. Users sign in with email and password. There are three roles: Super admin (service owner; manages all companies and creates the first administrator for each company; the first super admin is created by a console command during installation; super admins cannot be created through the UI), Company admin (manages their own company, including employees, photos, terminals, settings, and the company's users), Viewer (read-only access to their own company's events, timesheets, and reports). Every user except the super admin belongs to exactly one company. New users are added by email invitation and set their own password. The service needs password recovery, user deactivation (users are never deleted), and protection against password guessing. A company must always have at least one active admin. Users of a deactivated company cannot sign in. Employees who pass through the terminal are not CRM users. Out of scope for now: two-factor authentication, Google/SSO sign-in, self-registration, custom roles."

## Clarifications

### Session 2026-09-27

- Q: Beyond creating companies, listing them, deactivating/reactivating them, and inviting the first company admin, what may a super admin do inside a company? → A: Nothing more (option A). The super admin has no access to a company's users (beyond the first-admin invitation), employees, photos, terminals, settings, events, timesheets, or reports. Once the first admin has accepted, the super admin cannot invite a replacement; a company whose admins lose their credentials relies on password recovery.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Sign in and sign out with email and password (Priority: P1)

An existing active user (super admin, company admin, or viewer) opens the service, enters their email and password, and gets access to the parts of the service their role allows. They can sign out at any time. Anyone who is not signed in cannot see any personal or biometric data.

**Why this priority**: Nothing else in the service may be reachable without authentication, because it holds personal and biometric data. This is the gate for every other feature.

**Independent Test**: With one active user seeded, sign in with correct credentials and reach a protected page; sign out and confirm the protected page is no longer reachable; attempt access without signing in and confirm it is refused.

**Acceptance Scenarios**:

1. **Given** an active user of an active company, **When** they submit their correct email and password, **Then** they are signed in and land in the service.
2. **Given** an active super admin, **When** they submit their correct email and password, **Then** they are signed in.
3. **Given** any visitor who is not signed in, **When** they request any page or data other than sign-in, invitation acceptance, or password recovery, **Then** access is refused and no personal data is returned.
4. **Given** a signed-in user, **When** they sign out, **Then** their session ends and further requests with that session are refused.
5. **Given** a sign-in attempt with a wrong password or an unknown email, **When** it is submitted, **Then** the same generic failure message is shown in both cases and the user is not signed in.
6. **Given** a deactivated user, **When** they submit their correct email and password, **Then** sign-in is refused.
7. **Given** an active user whose company is deactivated, **When** they submit their correct email and password, **Then** sign-in is refused.

---

### User Story 2 - Role-based access within the user's own company (Priority: P1)

Once signed in, each user can do only what their role allows. A company admin manages their own company (employees, photos, terminals, settings, company users). A viewer can only read their own company's events, timesheets, and reports. Neither can see or change anything that belongs to another company.

**Why this priority**: Authentication alone does not protect biometric data; tenant isolation and role limits are the core privacy guarantee of the service.

**Independent Test**: Seed two companies, each with an admin and a viewer. For each user, attempt every category of action (read and change) against their own company and against the other company, and confirm only role-permitted actions on their own company succeed.

**Acceptance Scenarios**:

1. **Given** a signed-in viewer, **When** they open their company's events, timesheets, or reports, **Then** the data is shown.
2. **Given** a signed-in viewer, **When** they attempt to create, change, deactivate, or delete anything (employees, photos, terminals, settings, users, events, timesheets), **Then** the action is refused.
3. **Given** a signed-in viewer, **When** they attempt to open employee management, photos, terminals, settings, or user management, **Then** access is refused.
4. **Given** a signed-in company admin, **When** they manage employees, photos, terminals, settings, or users of their own company, **Then** the action is allowed.
5. **Given** a signed-in company admin or viewer of company A, **When** they request any data or action belonging to company B, **Then** the request is refused and nothing about company B is revealed, including whether the requested record exists.
6. **Given** a signed-in company admin or viewer, **When** they attempt any company-level administration reserved for super admins (creating companies, deactivating companies, viewing the list of all companies), **Then** the action is refused.
7. **Given** a signed-in super admin, **When** they request any company's users, employees, photos, terminals, settings, events, timesheets, or reports, **Then** the request is refused.
8. **Given** a signed-in super admin and a company whose first admin has already accepted the invitation, **When** they try to invite, re-send or revoke an invitation, deactivate or reactivate a user, or change a user's role in that company, **Then** the action is refused.

---

### User Story 3 - Invite users and accept an invitation (Priority: P1)

A super admin creates a company and invites its first company admin by email. A company admin invites further users of their own company by email and chooses the role (company admin or viewer). The invited person receives an email with a link, opens it, sets their own password, and becomes an active user of that company.

**Why this priority**: Without invitations there is no way to get any company user into the service, since self-registration is out of scope.

**Independent Test**: As a super admin, create a company and invite an admin; follow the invitation link, set a password, and sign in as that admin; then as the admin invite a viewer and repeat.

**Acceptance Scenarios**:

1. **Given** a signed-in super admin, **When** they create a company and enter the first admin's email, **Then** the company is created and an invitation email is sent to that address with the company admin role.
2. **Given** a signed-in company admin, **When** they invite an email address with the role company admin or viewer, **Then** an invitation email is sent and the pending invitation is visible in their company's user list.
3. **Given** a valid, unexpired invitation, **When** the invitee opens the link and sets a password that meets the password rules, **Then** their account becomes active in the inviting company with the invited role and they can sign in.
4. **Given** an expired, revoked, or already-used invitation, **When** the invitee opens the link, **Then** they cannot set a password and are told the invitation is no longer valid.
5. **Given** an email address that already belongs to an existing user (active or deactivated), **When** anyone tries to invite it, **Then** the invitation is refused with an explanation.
6. **Given** a pending invitation, **When** the inviting company admin re-sends it, **Then** a new invitation email is sent and the previous link stops working.
7. **Given** a pending invitation, **When** a company admin of that company revokes it, **Then** its link stops working.
8. **Given** any signed-in user, **When** they try to invite someone with the super admin role, **Then** no such option exists and any such request is refused.

---

### User Story 4 - Recover a forgotten password (Priority: P2)

A user who forgot their password requests a reset link by entering their email. They receive an email with a single-use, time-limited link, open it, and set a new password. Their other sessions are ended.

**Why this priority**: Users will forget passwords; without recovery an admin would have no self-service way back in. It is not needed on day one of a demo, hence P2.

**Independent Test**: Request a reset for an active user, follow the emailed link, set a new password, sign in with it, and confirm the old password no longer works.

**Acceptance Scenarios**:

1. **Given** an active user of an active company (or an active super admin), **When** they request a password reset for their email, **Then** they receive a reset email with a single-use link.
2. **Given** any email address (existing, unknown, or deactivated), **When** a reset is requested, **Then** the service shows the same confirmation message, so it does not reveal whether an account exists.
3. **Given** a deactivated user, a user of a deactivated company, or an unknown email, **When** a reset is requested, **Then** no reset email is sent.
4. **Given** a valid, unexpired reset link, **When** the user sets a new password that meets the password rules, **Then** the password is changed, the link cannot be used again, and all of that user's existing sessions are ended.
5. **Given** an expired or already-used reset link, **When** it is opened, **Then** the password cannot be changed and the user is told to request a new link.

---

### User Story 5 - Deactivate and reactivate company users (Priority: P2)

A company admin deactivates a user of their own company who should no longer have access. The user is never deleted; their account and history remain. A deactivated user cannot sign in, and any active sessions end immediately. The admin can reactivate the user later. The service never lets a company lose its last active admin.

**Why this priority**: Removing access for departed staff is essential for privacy, but the service is usable for a first company before anyone leaves.

**Independent Test**: As a company admin, deactivate a viewer who is signed in elsewhere; confirm that viewer's session stops working and they cannot sign in; reactivate them and confirm they can sign in again.

**Acceptance Scenarios**:

1. **Given** a signed-in company admin, **When** they deactivate another user of their own company, **Then** that user is marked deactivated, remains in the user list, cannot sign in, and all of their active sessions are ended.
2. **Given** a deactivated user of their own company, **When** the company admin reactivates them, **Then** the user can sign in again with their existing password and role.
3. **Given** a company with exactly one active company admin, **When** anyone tries to deactivate that admin, **Then** the action is refused with an explanation that the company must keep at least one active admin.
4. **Given** a company with exactly one active company admin, **When** anyone tries to change that admin's role to viewer, **Then** the action is refused with the same explanation.
5. **Given** a company with two or more active company admins, **When** a company admin deactivates or demotes one of the others or themselves, **Then** the action is allowed as long as at least one active admin remains.
6. **Given** any signed-in user, **When** they try to delete a user, **Then** no delete option exists and any such request is refused.

---

### User Story 6 - Change a company user's role (Priority: P3)

A company admin changes a user of their own company between company admin and viewer. The change takes effect on the user's next request.

**Why this priority**: Useful for day-to-day administration, but a user can also be deactivated and re-invited as a workaround, so it is lowest priority.

**Independent Test**: Promote a viewer to company admin and confirm they can now manage employees; demote them back and confirm they are read-only again.

**Acceptance Scenarios**:

1. **Given** a signed-in company admin, **When** they change a viewer of their own company to company admin, **Then** that user gains company admin access on their next request.
2. **Given** a signed-in company admin and a company with two or more active admins, **When** they change another admin to viewer, **Then** that user loses admin access on their next request.
3. **Given** any signed-in user, **When** they try to assign the super admin role or any role other than company admin or viewer, **Then** the action is refused.

---

### User Story 7 - Install the first super admin and deactivate companies (Priority: P2)

During installation, an operator runs a console command that creates the first super admin with an email and password. A super admin can deactivate a company, after which none of its users can sign in and their sessions end.

**Why this priority**: The first super admin is a prerequisite for creating any company, but it is a one-time installation step. Company deactivation is needed once a customer leaves.

**Independent Test**: Run the installation command on an empty installation, sign in as the created super admin, create a company with an admin, then deactivate the company and confirm the admin can no longer sign in.

**Acceptance Scenarios**:

1. **Given** an installation, **When** the operator runs the super admin creation console command with an email and a password that meets the password rules, **Then** a super admin with that email is created and can sign in.
2. **Given** the console command, **When** it is given an email that already belongs to a user or a password that breaks the password rules, **Then** no user is created and the command reports the reason.
3. **Given** any signed-in user, **When** they look for a way to create a super admin in the UI, **Then** none exists and any such request is refused.
4. **Given** a signed-in super admin, **When** they deactivate a company, **Then** none of that company's users can sign in and all of their active sessions end.
5. **Given** a signed-in super admin, **When** they reactivate a deactivated company, **Then** the company's active users can sign in again.

---

### Edge Cases

- A company has been created but its first admin has not yet accepted the invitation: the company has zero active admins. The "at least one active admin" rule applies only to actions that would remove the last active admin; until a first-admin invitation is accepted, the super admin can re-send or revoke the first-admin invitation and invite a replacement first admin; after that, the super admin has no further say over the company's users.
- All admins of a company lose their credentials after the first admin has accepted: the super admin cannot invite a replacement or act inside the company; access is regained only through password recovery.
- The same person needs access to two companies: not supported by one account, because every non-super-admin user belongs to exactly one company and email is unique across the service; they need a separate email address per company.
- Two company admins try to deactivate or demote each other at the same moment while they are the only two active admins: at most one of the actions succeeds, so the company still has at least one active admin.
- A user is deactivated, their company is deactivated, or their role is lowered while they are signed in: the change takes effect on their next request, not at their next sign-in.
- A user with a pending password reset link is deactivated: the link can no longer be used.
- An invitation is accepted after the inviting admin was deactivated: the invitation stays valid, because it belongs to the company, not to the admin who sent it.
- Email addresses that differ only by letter case are treated as the same address, for sign-in, invitations, and uniqueness.
- Repeated failed sign-in attempts for one account: further attempts for that account are temporarily blocked (limits in `constraints.md`), and the block message does not reveal whether the password was close or whether the account exists.
- Repeated failed attempts spread across many accounts from one source: attempts from that source are also temporarily blocked.
- Terminal-recognised employees try to sign in: they have no user accounts, so sign-in fails like any unknown email.

## Requirements *(mandatory)*

### Functional Requirements

**Authentication**

- **FR-001**: System MUST authenticate users by email and password only.
- **FR-002**: System MUST refuse every request for personal or biometric data, and every other service function, from anyone who is not signed in, except sign-in, invitation acceptance, and password recovery.
- **FR-003**: System MUST refuse sign-in for deactivated users and for users whose company is deactivated.
- **FR-004**: System MUST show the same failure message for a wrong password, an unknown email, a deactivated user, and a user of a deactivated company, so that failures do not reveal whether an account exists or its state.
- **FR-005**: System MUST let a signed-in user sign out, ending that session.
- **FR-006**: System MUST apply user deactivation, company deactivation, and role changes to already signed-in users on their next request.
- **FR-007**: System MUST treat email addresses case-insensitively and keep every email unique across all users of the service, including super admins.

**Password guessing protection**

- **FR-008**: System MUST temporarily block sign-in attempts for an account after the number of consecutive failed attempts defined in `constraints.md`, for the block period defined there.
- **FR-009**: System MUST temporarily block sign-in attempts from a single source that exceeds the failed-attempt limit defined in `constraints.md`, regardless of which accounts are targeted.
- **FR-010**: System MUST limit how often password reset emails can be requested for one email address, as defined in `constraints.md`.
- **FR-011**: System MUST record failed sign-in attempts and blocks for security review, without recording the submitted password.

**Passwords**

- **FR-012**: System MUST require every password (set via invitation, reset, or the installation command) to meet the length rules in `constraints.md`.
- **FR-013**: System MUST never show, send, or record a user's password in readable form.

**Roles and access**

- **FR-014**: System MUST support exactly three roles: super admin, company admin, and viewer. Custom roles are not supported.
- **FR-015**: Every company admin and viewer MUST belong to exactly one company; a super admin MUST NOT belong to any company.
- **FR-016**: A company admin MUST be able to manage employees, employee photos, terminals, settings, and users of their own company, and nothing of any other company.
- **FR-017**: A viewer MUST be able to read events, timesheets, and reports of their own company, and MUST NOT be able to change anything or open employee, photo, terminal, settings, or user management.
- **FR-018**: System MUST refuse any access by a company admin or viewer to another company's data without revealing whether the requested record exists.
- **FR-019**: A super admin MUST be able to create companies, view all companies, deactivate and reactivate companies, and invite the first company admin of a company.
- **FR-020**: Beyond FR-019, a super admin MUST NOT be able to read or change a company's users, employees, employee photos, terminals, settings, events, timesheets, or reports. The only exception is the first-admin invitation: until a first-admin invitation of that company is accepted, the super admin MAY re-send or revoke it and invite a replacement first admin; after acceptance, the super admin MUST NOT invite, re-send, revoke, deactivate, reactivate, or change the role of any user of that company.

**Invitations**

- **FR-021**: New company users MUST be added only by email invitation; self-registration is not available.
- **FR-022**: A company admin MUST be able to invite users to their own company with the role company admin or viewer.
- **FR-023**: When a super admin creates a company, they MUST provide the email of the first company admin, and the system MUST send that person an invitation with the company admin role.
- **FR-024**: An invitation link MUST be single-use and expire after the period defined in `constraints.md`.
- **FR-025**: Accepting a valid invitation MUST let the invitee set their own password and activate their account in the inviting company with the invited role.
- **FR-026**: System MUST refuse to invite an email that already belongs to an existing user, active or deactivated.
- **FR-027**: A pending invitation MUST be re-sendable and revocable by a company admin of the inviting company, and a pending first-admin invitation also by a super admin as defined in FR-020; re-sending MUST invalidate the previous link.
- **FR-028**: System MUST show pending invitations, with their role and expiry, in the company's user list.
- **FR-029**: No UI or API path MUST allow creating a super admin or assigning the super admin role.

**Password recovery**

- **FR-030**: Any user MUST be able to request a password reset by email; the confirmation shown MUST be identical whether or not the email belongs to an eligible account.
- **FR-031**: System MUST send a reset email only to active users of active companies and to active super admins.
- **FR-032**: A reset link MUST be single-use and expire after the period defined in `constraints.md`; it MUST stop working if the user is deactivated or their company is deactivated.
- **FR-033**: Setting a new password via reset MUST end all of that user's existing sessions.

**Deactivation and the last-admin rule**

- **FR-034**: A company admin MUST be able to deactivate and reactivate users of their own company, including themselves, subject to FR-037.
- **FR-035**: Users MUST never be deleted; a deactivated user MUST remain visible in their company's user list with the deactivated state.
- **FR-036**: Deactivating a user MUST end all of that user's active sessions and invalidate their pending reset links.
- **FR-037**: System MUST refuse any deactivation or role change that would leave a company that currently has at least one active company admin with none, including when two such changes are submitted at the same time.
- **FR-038**: A company admin MUST be able to change the role of a user of their own company between company admin and viewer, subject to FR-037.
- **FR-039**: Deactivating a company MUST end all active sessions of that company's users; reactivating it MUST let its active users sign in again.

**Installation**

- **FR-040**: System MUST provide a console command that creates a super admin from an email and a password, refusing an email already in use or a password that breaks the password rules.

### Key Entities

- **User**: A person who signs in to the CRM. Has an email (unique across the service, case-insensitive), a password, a role (super admin, company admin, or viewer), a state (active or deactivated), and — unless a super admin — exactly one company. Never deleted. Not the same as an employee recognised by terminals.
- **Company**: A customer organisation served by the service. Has a state (active or deactivated). Owns its company admins and viewers, and (outside this feature) its employees, photos, terminals, settings, events, timesheets, and reports.
- **Role**: One of three fixed values: super admin, company admin, viewer. Defines which actions a user may perform and on which company's data.
- **Invitation**: A pending offer for an email address to join a specific company with a specific role. Has an expiry, and a state (pending, accepted, expired, or revoked). Single-use.
- **Password reset request**: A single-use, time-limited permission for one user to set a new password.
- **Session**: A signed-in state of one user; ends on sign-out, password reset, user deactivation, or company deactivation.
- **Security event**: A record of a failed sign-in attempt or a sign-in block, kept for security review, never containing the password.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of requests from people who are not signed in are refused for every function except sign-in, invitation acceptance, and password recovery.
- **SC-002**: 100% of attempts by a company admin or viewer to read or change another company's data are refused, verified across every protected function.
- **SC-003**: 100% of change attempts by viewers are refused.
- **SC-010**: 100% of super admin requests for a company's users, employees, photos, terminals, settings, events, timesheets, or reports are refused, except the first-admin invitation actions allowed by FR-020.
- **SC-004**: An invited user can go from opening the invitation email to being signed in in under 2 minutes.
- **SC-005**: A user who forgot their password can regain access in under 3 minutes from requesting the reset.
- **SC-006**: A deactivated user, or a user of a deactivated company, loses access on their very next request after the deactivation.
- **SC-007**: No sequence of user actions, including simultaneous ones, leaves a company that had an active admin with zero active admins.
- **SC-008**: After the failed-attempt limit in `constraints.md`, 100% of further sign-in attempts for that account are blocked until the block period ends, even with the correct password.
- **SC-009**: Sign-in failures and password reset confirmations are indistinguishable between existing and non-existing email addresses.

## Assumptions

- Additional super admins, beyond the first, are also created only with the installation console command; there is no other way to create one.
- Deactivation is reversible: a company admin can reactivate a user of their company, and a super admin can reactivate a company. The description forbids deletion but does not forbid reactivation.
- A company admin can deactivate themselves or change their own role, as long as another active admin remains.
- The "at least one active admin" rule protects companies that already have an active admin; a newly created company has none until its first invitee accepts.
- Email is unique across the whole service, so one person who needs access to two companies uses two email addresses.
- Boundary values (password length, failed-attempt limits, block period, invitation and reset link lifetimes, reset request rate) use common industry defaults recorded in `constraints.md` and can be changed there.
- Invitation and password reset emails are delivered by the service's existing outgoing email capability.
- Management of company details other than creation, listing, and activation state, and management of employees, photos, terminals, settings, events, timesheets, and reports, are separate features; this feature only defines who may access them.
- Out of scope: two-factor authentication, Google/SSO sign-in, self-registration, custom roles, and a user-facing audit log viewer.
