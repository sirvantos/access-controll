Data isolation between companies in a CRM service for access control and time tracking.

The service hosts multiple companies in one system and stores their personal data: employee face photos, entry/exit history, and working hours. One company's data must never be visible or accessible to users of another company.

Every record (users, employees, photos, terminals, events, settings, reports, exports) belongs to exactly one company. A user sees and modifies only their own company's data in every part of the service: lists, search, counters, reports, and export files. Attempting to open another company's record by direct link or ID must look like "not found". New records are automatically assigned to the user's company. Photos and event snapshots are not accessible without authentication or through public links.

The super admin sees all companies but works with company data only after explicitly selecting a company. The selected company is always shown in the UI, and the super admin's actions are logged.

Events from a terminal belong to the company that owns the terminal. Employee numbers are unique within a company, so identical numbers in different companies must never be mixed up.

Isolation must work by default, including for all future parts of the service, rather than depending on care taken when building each page.
