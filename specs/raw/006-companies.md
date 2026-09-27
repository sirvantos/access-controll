Company management for a CRM service for access control and time tracking.

The service serves multiple client companies. A company is the core unit of the service: it owns users, employees, face recognition terminals, entry/exit events, and reports.

The super admin creates companies, sees a searchable list of them, and can deactivate or reactivate a company. Companies are never deleted. Their data is kept when they are deactivated.

A company has a name, a time zone (default Asia/Almaty), and optional details: business ID (BIN), contact person, phone, email. The time zone is needed to display entry/exit times correctly and to assign each event to the correct day.

A company has working day settings used to calculate late arrivals and hours worked: start and end of the working day, working days of the week, break duration and whether it is deducted, and a lateness grace period in minutes. Defaults are 09:00–18:00, Monday–Friday, 60-minute break. Changing the settings must not change calculations for past days.

A company admin can edit the details and settings of their own company only. A viewer sees them read-only.

Out of scope for now: night shifts, individual employee schedules, holiday calendar, branches within a company, pricing plans and billing.
