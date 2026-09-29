<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\PublicApi;

final class CrossCompanyFunctions
{
    public const string AdminListCompanies = 'admin.list_companies';

    public const string AdminCreateCompany = 'admin.create_company';

    public const string AdminDeactivateCompany = 'admin.deactivate_company';

    public const string AdminReactivateCompany = 'admin.reactivate_company';

    public const string AdminFirstAdminInvitations = 'admin.first_admin_invitations';

    public const string AdminSelectCompany = 'admin.select_company';

    public const string IdentitySignIn = 'identity.sign_in';

    public const string IdentityEmailUniqueness = 'identity.email_uniqueness';

    public const string IdentityCreateSuperAdmin = 'identity.create_super_admin';

    public const string IdentityPasswordReset = 'identity.password_reset';

    public const string IdentityEndCompanySessions = 'identity.end_company_sessions';

    public const string IdentitySessionUser = 'identity.session_user';

    public const string CompaniesDirectoryBeforeContext = 'companies.directory_before_context';

    public const string IdentityShowInvitation = 'identity.show_invitation';

    public const string IdentityAcceptInvitation = 'identity.accept_invitation';

    public const string TenancyRecordTerminalEventLoadTerminal = 'tenancy.record_terminal_event.load_terminal';

    public const string TimeZonesList = 'time_zones.list';

    public const string AuthMeSignOut = 'auth.me_sign_out';

    /**
     * @return list<string>
     */
    public static function identifiers(): array
    {
        return [
            self::AdminListCompanies,
            self::AdminCreateCompany,
            self::AdminDeactivateCompany,
            self::AdminReactivateCompany,
            self::AdminFirstAdminInvitations,
            self::AdminSelectCompany,
            self::IdentitySignIn,
            self::IdentityEmailUniqueness,
            self::IdentityCreateSuperAdmin,
            self::IdentityPasswordReset,
            self::IdentityEndCompanySessions,
            self::IdentitySessionUser,
            self::CompaniesDirectoryBeforeContext,
            self::IdentityShowInvitation,
            self::IdentityAcceptInvitation,
            self::TenancyRecordTerminalEventLoadTerminal,
            self::TimeZonesList,
            self::AuthMeSignOut,
        ];
    }
}
