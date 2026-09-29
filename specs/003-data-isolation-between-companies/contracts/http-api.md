# HTTP API Contract: Data Isolation Between Companies

**Feature**: [../spec.md](../spec.md) · **Data**: [../data-model.md](../data-model.md) · **Limits**: [../constraints.md](../constraints.md)

Conventions, auth, CSRF, `throttle:api`, pagination `links`/`meta`, and error envelopes are those of [feature 001 http-api.md](../../001-user-authentication-and-roles/contracts/http-api.md), except as noted below.

## Additive conventions

- **Company-data routes**: `/api/v1/company` and `/api/v1/company/*`. Middleware: `auth:sanctum`, `current-session`, `role:` as today plus `super_admin` where a company admin (or viewer on GET) is allowed, then `company-context`.
- **Header** `X-Company-Context`: integer company id. Required for a super admin on company-data routes. Ignored for company admin and viewer (their company is the actor's).
- **404** for a signed-in caller's other-company record, unknown id, or unknown media UUID: same JSON `{ "message": "..." }` as feature 001, **identical body** for a Globex id and an unused id (FR-006). Guests and signed-out sessions get the existing **401** auth envelope, not that 404.
- **409** `error_code = company_not_selected`: super admin company-data request with no valid selection (FR-016). Not used for missing records.

## Resources

| Resource | Shape |
|----------|-------|
| `SelectedCompanyResource` | `{ id, name }` wrapped in `{ data }` |
| Media GET 200 | binary body through a `JsonResource`, `Content-Type` of the stored file |
| Existing company-data resources | unchanged shapes from features 001 and 002 |

`GET /me` is unchanged (`company_id` remains null for a super admin). Selection is not stored on the user.

## Super admin selection (`role:super_admin`; no `company-context` middleware)

| Method & path | Body | Success | Errors | Req. |
|---------------|------|---------|--------|------|
| `POST /admin/selected-company` | `company_id` (required integer, must exist) | `200 SelectedCompanyResource`; session allow-list updated; action record `selected_company` | `404` unknown company (same as other unknown company ids). `422` invalid id. | FR-016, FR-019, FR-020 |
| `DELETE /admin/selected-company` | none | `200 OkResource` | none beyond auth/role | FR-019 |

`GET /admin/companies` (list, search, create, deactivate, reactivate) and first-admin invitation routes stay as in features 001 and 002: no selection required (FR-015, FR-024).

## Company-data routes (existing, behaviour change for super admin)

All existing `/company` and `/company/*` routes from features 001 and 002:

- **company_admin / viewer**: unchanged data scope (own company). Sending `X-Company-Context` for another company does not change scope (FR-002).
- **super_admin** without a valid header+allow-list entry: `409 company_not_selected` (replaces today's `403` on these routes).
- **super_admin** with company A selected: same success and business errors as a company admin of A (FR-022), including `409 last_active_admin`. Records of company B: `404` (FR-018). Deactivated A is allowed (FR-022).

Submitting a `company_id` field on create/update bodies (if a client sends one) is ignored; the record stays in the context company (FR-002, FR-003).

## Media (`company-context`)

| Method & path | Roles | Success | Errors | Req. |
|---------------|-------|---------|--------|------|
| `GET /company/media/{public_id}` | `company_admin`; `viewer` only for kinds allowed in the data model; `super_admin` with selection as company admin | `200` file bytes | `401` guest or after sign-out. `403` signed-in role not allowed for that kind (viewer + employee photo). `404` unknown UUID, other company, or not found — same JSON as each other | FR-008, FR-009, FR-010 |

`public_id` is a UUID. Sequential `id` is not accepted.

There is no `GET` that lists all media of another company.

## Field validation (422 `errors.{field}`)

| Field | Rule |
|-------|------|
| `company_id` (select) | required, integer, exists in `companies` |

Messages from `lang/{en,ru}/tenancy.php` and `validation.php`. Both locales required.

## OpenAPI

Mirror this file into `public/swagger.yaml`. Do not document `/_test/*` probe routes.
