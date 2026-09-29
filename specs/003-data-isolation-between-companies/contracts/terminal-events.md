# Terminal Events Contract (domain Action)

**Feature**: [../spec.md](../spec.md) · **Data**: [../data-model.md](../data-model.md)

There is no public HTTP ingest in this feature. Later transport features must call this Action unchanged.

## `RecordTerminalEventAction`

**Input** (primitives / same-module models, no HTTP types):

- `terminalId` (int)
- `employeeNumber` (string, 1–32 characters, already trimmed by the future transport)
- optional snapshot bytes or `CompanyMedia` already stored in the terminal's company

**Behaviour**:

1. Load the terminal **without** company context (`tenancy.record_terminal_event.load_terminal`). If missing → return without inserting (FR-013).
2. `CompanyContext::run(terminal.companyId, …)`.
3. Insert `AccessEvent` with that `company_id` and `terminal_id` (FR-011).
4. Find `Employee` where `employee_number` matches **inside this context only**. If found, set `employee_id`. If the same number exists only in another company, leave `employee_id` null (FR-012).
5. Never write `company_id` of another company.

**Proof**: `tests/Feature/Modules/Tenancy/RecordTerminalEventTest.php` with employee `17` in Acme and Globex, event from an Acme terminal.

## `CreateEmployeeAction` (uniqueness)

**Input**: name, employee number (company from context).

Same number in the context company → `EmployeeNumberTakenException` (`422`). Same number in Globex → Acme create succeeds; the 422 body does not mention Globex (FR-014).
