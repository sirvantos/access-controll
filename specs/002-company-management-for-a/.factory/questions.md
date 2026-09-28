# Open Questions: 002-company-management-for-a

## Q1: When does a working day settings change start to apply? (spec FR-016)

**Context**: The description says "Changing the settings must not change calculations for past days." It does not say whether the current day counts as past, or whether an admin can schedule a change.

**What we need to know**: From which moment does a saved change to the working day settings apply to late-arrival and hours-worked calculations?

| Option | Answer | Implications |
|--------|--------|--------------|
| A | From the next day in the company's time zone | Today and every earlier day keep the old settings. Today's results never shift while employees are at work. |
| B | Immediately, including the current day | Only days before today are frozen. Today's lateness and hours are recalculated with the new settings. |
| C | From an effective date the admin chooses (today or later) | Changes can be scheduled ahead. The admin needs a date field, and the service must keep upcoming versions. |
| Custom | Describe the rule | — |

**Answer**: A

## Q2: What happens to past days when the company's time zone changes? (spec FR-012)

**Context**: The description says the time zone is used "to display entry/exit times correctly and to assign each event to the correct day", and that settings changes must not change past days. It does not say whether a time zone change counts as a settings change.

**What we need to know**: When a company admin changes the time zone, what happens to days already recorded?

| Option | Answer | Implications |
|--------|--------|--------------|
| A | Past days keep the time zone that applied then; the new one applies from the moment the change applies (as in Q1) | Past results and their displayed times never change. The service keeps a time zone history like the settings history. |
| B | Past days keep their day assignment and results, but all times are displayed in the new time zone | Results stay the same, but a past event may be shown at a time that looks inconsistent with its day. |
| C | Everything is recalculated in the new time zone, past days included | Past days' results can change, which conflicts with "must not change calculations for past days". |
| D | The time zone cannot be changed after the company is created | Simplest option. A company created with the wrong time zone cannot be fixed. |
| Custom | Describe the rule | — |

**Answer**: A

## Q3: What are the defaults for break deduction and the lateness grace period? (spec FR-014)

**Context**: The description gives these defaults: "09:00–18:00, Monday–Friday, 60-minute break". It lists "whether [the break] is deducted" and "a lateness grace period in minutes" as settings, but gives no defaults for either.

**What we need to know**: What should a new company have for break deduction and the grace period?

| Option | Answer | Implications |
|--------|--------|--------------|
| A | Break deducted; grace period 0 minutes | An 8-hour paid day by default. Any arrival after 09:00 counts as late. |
| B | Break deducted; grace period 5 minutes | An 8-hour paid day. Arrivals up to 09:05 do not count as late. |
| C | Break not deducted; grace period 0 minutes | A 9-hour day counted in full. Any arrival after 09:00 counts as late. |
| Custom | Give both values | — |

**Answer**: A
