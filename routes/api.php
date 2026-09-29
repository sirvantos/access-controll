<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ClearSelectedCompanyController;
use App\Http\Controllers\Admin\CreateCompanyController;
use App\Http\Controllers\Admin\DeactivateCompanyController;
use App\Http\Controllers\Admin\InviteFirstAdminController;
use App\Http\Controllers\Admin\ListCompaniesController;
use App\Http\Controllers\Admin\ListFirstAdminInvitationsController;
use App\Http\Controllers\Admin\ReactivateCompanyController;
use App\Http\Controllers\Admin\RejectCompanyDeletionController;
use App\Http\Controllers\Admin\ResendFirstAdminInvitationController;
use App\Http\Controllers\Admin\RevokeFirstAdminInvitationController;
use App\Http\Controllers\Admin\SelectCompanyController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\ShowCurrentUserController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\Company\ChangeCompanyUserRoleController;
use App\Http\Controllers\Company\DeactivateCompanyUserController;
use App\Http\Controllers\Company\InviteCompanyUserController;
use App\Http\Controllers\Company\ListCompanyInvitationsController;
use App\Http\Controllers\Company\ListCompanyUsersController;
use App\Http\Controllers\Company\ReactivateCompanyUserController;
use App\Http\Controllers\Company\ResendCompanyInvitationController;
use App\Http\Controllers\Company\RevokeCompanyInvitationController;
use App\Http\Controllers\Company\ShowCompanyMediaController;
use App\Http\Controllers\Company\ShowCompanyProfileController;
use App\Http\Controllers\Company\ShowWorkingDaySettingsController;
use App\Http\Controllers\Company\UpdateCompanyProfileController;
use App\Http\Controllers\Company\UpdateWorkingDaySettingsController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\ShowInvitationController;
use App\Http\Controllers\ListTimeZonesController;
use App\Http\Middleware\ResolveCompanyMediaKind;
use Illuminate\Support\Facades\Route;

Route::post('/auth/sign-in', SignInController::class);
Route::post('/auth/forgot-password', ForgotPasswordController::class);
Route::post('/auth/reset-password', ResetPasswordController::class);
Route::get('/invitations/{token}', ShowInvitationController::class);
Route::post('/invitations/{token}/accept', AcceptInvitationController::class);

Route::middleware(['auth:sanctum', 'current-session'])->group(static function (): void {
    Route::get('/me', ShowCurrentUserController::class);
    Route::post('/auth/sign-out', SignOutController::class);
});

Route::middleware(['auth:sanctum', 'current-session', 'role:super_admin,company_admin'])
    ->get('/time-zones', ListTimeZonesController::class);

Route::middleware(['auth:sanctum', 'current-session', 'role:company_admin,viewer,super_admin', 'company-context'])
    ->get('/company', ShowCompanyProfileController::class);

Route::middleware(['auth:sanctum', 'current-session', 'role:company_admin,viewer,super_admin', 'company-context', ResolveCompanyMediaKind::class])
    ->get('/company/media/{public_id}', ShowCompanyMediaController::class);

Route::middleware(['auth:sanctum', 'current-session', 'role:company_admin,super_admin', 'company-context'])
    ->patch('/company', UpdateCompanyProfileController::class);

Route::middleware(['auth:sanctum', 'current-session', 'role:company_admin,viewer,super_admin', 'company-context'])
    ->get('/company/working-day-settings', ShowWorkingDaySettingsController::class);

Route::middleware(['auth:sanctum', 'current-session', 'role:company_admin,super_admin', 'company-context'])
    ->patch('/company/working-day-settings', UpdateWorkingDaySettingsController::class);

Route::middleware(['auth:sanctum', 'current-session', 'role:company_admin,super_admin', 'company-context'])
    ->prefix('company')
    ->group(static function (): void {
        Route::get('/users', ListCompanyUsersController::class);
        Route::patch('/users/{user}', ChangeCompanyUserRoleController::class)->whereNumber('user');
        Route::post('/users/{user}/deactivate', DeactivateCompanyUserController::class)->whereNumber('user');
        Route::post('/users/{user}/reactivate', ReactivateCompanyUserController::class)->whereNumber('user');
        Route::get('/invitations', ListCompanyInvitationsController::class);
        Route::post('/invitations', InviteCompanyUserController::class);
        Route::post('/invitations/{invitation}/resend', ResendCompanyInvitationController::class);
        Route::post('/invitations/{invitation}/revoke', RevokeCompanyInvitationController::class);
    });

Route::middleware(['auth:sanctum', 'current-session', 'role:super_admin'])
    ->prefix('admin')
    ->group(static function (): void {
        Route::post('/selected-company', SelectCompanyController::class);
        Route::delete('/selected-company', ClearSelectedCompanyController::class);
        Route::get('/companies', ListCompaniesController::class);
        Route::post('/companies', CreateCompanyController::class);
        Route::post('/companies/{company}/deactivate', DeactivateCompanyController::class)->whereNumber('company');
        Route::post('/companies/{company}/reactivate', ReactivateCompanyController::class)->whereNumber('company');
        Route::delete('/companies/{company}', RejectCompanyDeletionController::class)->whereNumber('company');
    });

Route::middleware(['auth:sanctum', 'current-session', 'role:super_admin', 'awaiting-first-admin'])
    ->prefix('admin/companies/{company}')
    ->whereNumber('company')
    ->group(static function (): void {
        Route::get('/invitations', ListFirstAdminInvitationsController::class);
        Route::post('/invitations', InviteFirstAdminController::class);
        Route::post('/invitations/{invitation}/resend', ResendFirstAdminInvitationController::class)->whereNumber('invitation');
        Route::post('/invitations/{invitation}/revoke', RevokeFirstAdminInvitationController::class)->whereNumber('invitation');
    });

Route::fallback(fn () => abort(404));
