# Quickstart & Validation: User Authentication and Roles

**Feature**: [spec.md](./spec.md) · **Contracts**: [contracts/http-api.md](./contracts/http-api.md), [contracts/console.md](./contracts/console.md), [contracts/spa-routes.md](./contracts/spa-routes.md) · **Limits**: [constraints.md](./constraints.md)

This is a validation guide. Implementation detail belongs in `tasks.md`.

## Prerequisites

- PHP 8.4, Composer, and Node `^20.19 || >=22.12`.
- `composer install` and `npm install` (they bring in `laravel/sanctum` and `vue-router`; see plan Dependencies).
- `.env` from `.env.example`, `php artisan key:generate`, and `php artisan migrate`.
- The mail driver is `log` locally, so invitation and reset links show up in `storage/logs/laravel.log`.
- A queue worker (`php artisan queue:work`) is needed for emails outside tests, because notifications are queued.

## Automated gates (Definition of Done)

```bash
make verify
```

This runs Pint, Prettier, Larastan level 8, deptrac, vue-tsc, the Vite build, Pest, and Vitest. For targeted runs:

```bash
php artisan test --compact tests/Feature/Modules/Identity
php artisan test --compact tests/Feature/Modules/Companies
npm run test:run
```

Test conventions (research R13):
- Send a stateful `Referer` through the `tests/Support/Identity` helper.
- Use `actingAs($user, 'web')`, `Notification::fake()`, and `Carbon::setTestNow()` for expiry and block periods.
- Compare messages with `__()` under both `en` and `ru`.
- Each domain exception keeps a `Log::fake()` silence proof (rule `exception-shouldnt-report`).

## Scenario map

Each acceptance scenario in `spec.md` needs a Pest feature test. The table maps each user story to its test file.

| Story | Test file (`tests/Feature/Modules/...`) | Key checks |
|-------|------------------------------------------|------------|
| US1 sign in/out | `Identity/SignInTest.php`, `Identity/SignOutTest.php`, `Identity/GuestAccessTest.php` | An active user or super admin signs in and gets 200. A wrong password and an unknown email return an identical 422 body. A deactivated user or a user of a deactivated company gets the same 422. A guest gets 401 on `/me` and every protected route. After sign-out, `/me` returns 401. |
| US1 guessing protection | `Identity/SignInThrottleTest.php` | After the per-account limit of failures, even the correct password gets 429 until the block period passes, and then it gets 200. An unknown email behaves the same way. The per-source limit across different emails leads to 429. Security events are recorded, and none of them contains the password. |
| US2 roles & isolation | `Identity/RoleAccessTest.php`, `Identity/TenantScopingTest.php` | A viewer gets 403 on every `/company/*` and `/admin/*` route. A company admin gets 403 on `/admin/*`. A super admin gets 403 on `/company/*`. A company A admin gets 404 on company B's user and invitation ids. `role:` middleware is also checked on test-only routes for the viewer read and write categories. |
| US3 invitations | `Identity/InviteCompanyUserTest.php`, `Identity/AcceptInvitationTest.php`, `Identity/ResendRevokeInvitationTest.php`, `Companies/CreateCompanyTest.php` | A company is created together with its first-admin invitation. Inviting a registered email returns 422, and on company creation no company is left behind. Acceptance creates an active user. An expired, revoked, or used invitation returns 410. After a re-send, the old token returns 410 and the new one works. `super_admin` as a role returns 422. |
| US4 password recovery | `Identity/ForgotPasswordTest.php`, `Identity/ResetPasswordTest.php` | The response is identical for all email kinds. A notification is sent only to eligible users. Requests are throttled per email. A reset ends other sessions, meaning the old session's `/me` returns 401. An expired or used link returns 422. A deactivated user's link returns 422. |
| US5 deactivation | `Identity/DeactivateCompanyUserTest.php`, `Identity/ReactivateCompanyUserTest.php`, `Identity/LastActiveAdminTest.php` | A deactivated user's session returns 401 on the next request, even after a quick reactivation. The last admin cannot be deactivated or demoted (409), and this is also checked for two sequential calls under the lock. `DELETE` returns 405. |
| US6 role change | `Identity/ChangeCompanyUserRoleTest.php` | A promoted user can reach `/company/users` on the next request. A demoted user gets 403 there. |
| US7 install & company state | `Identity/CreateSuperAdminCommandTest.php`, `Companies/DeactivateCompanyTest.php`, `Companies/ReactivateCompanyTest.php`, `Identity/FirstAdminInvitationsTest.php` | The command creates a super admin, or fails with exit code 1 on a duplicate email or a bad password. Company deactivation makes users' sessions return 401 and blocks their sign-in. Reactivation allows sign-in again. Once the company has a user, super-admin invitation routes return 403. |
| Frontend | `resources/js/**/__tests__/*.spec.ts` | Router guards. Each page's success, 401, 409, 410, 422, and 429 states render `t()` keys. |

Acceptance scenarios about employees, photos, terminals, settings, events, timesheets, and reports (US2-1…4, US2-7) have no endpoints yet. This feature proves them through the `role:` middleware on test-only routes that each role category guards. Later features must put their routes behind the same middleware.

## Manual smoke run

1. `php artisan identity:create-super-admin owner@example.com`, then enter a password twice. Expect exit code 0.
2. Open `/sign-in` and sign in as the owner. You should land on `/companies`.
3. Create the company "Acme" with first-admin email `admin@acme.test`. Copy the invitation link from `laravel.log`.
4. Open the link, set a password, and sign in as `admin@acme.test`. You should land on `/company/users`.
5. Invite `viewer@acme.test` as a viewer, accept it, and sign in. Confirm there are no management links and that `/company/users` redirects to `/`.
6. As the admin, try to deactivate yourself while you are the only admin. Expect the last-admin explanation.
7. As the owner, deactivate Acme. The admin's open tab must get 401 on its next action, and signing in must fail with the generic message.
