# Console Contract: Create Super Admin

**Feature**: [../spec.md](../spec.md) (US7, FR-040) · **Limits**: [../constraints.md](../constraints.md)

## `php artisan identity:create-super-admin {email}`

- Registered by `App\Modules\Identity\IdentityServiceProvider`. The class is `App\Modules\Identity\Console\CreateSuperAdminCommand`.
- `email` argument: validated `required|string|email|max:255` and lowercased.
- Password: asked with a hidden prompt, entered twice. It is never accepted as an argument or option, so it does not end up in shell history (FR-013). Validated by `App\Support\Validation\PasswordRules` (8 to 128 characters).
- Calls `CreateSuperAdminAction`, which creates the user with `role = super_admin` and `company_id = null`.

| Case | Exit code | Output |
|------|-----------|--------|
| Valid email that is not registered and a valid password | `0` (success) | Confirmation that includes the email |
| Email already registered (any role, any case) | `1` (failure) | Localized `identity.email_already_registered`; no user is created |
| Password shorter than 8 or longer than 128 characters | `1` | The validation message; no user is created |
| The two password entries differ | `1` | Localized `validation.confirmed`; no user is created |
| Invalid email format | `1` | The validation message; no user is created |

The command never prints the password. It can also add extra super admins (spec Assumptions).
