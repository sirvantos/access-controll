<?php

declare(strict_types=1);

use App\Modules\Tenancy\PublicApi\CrossCompanyFunctions;

it('lists every named exception identifier from the isolation contract', function (): void {
    $expected = [
        'admin.list_companies',
        'admin.create_company',
        'admin.deactivate_company',
        'admin.reactivate_company',
        'admin.first_admin_invitations',
        'admin.select_company',
        'identity.sign_in',
        'identity.email_uniqueness',
        'identity.create_super_admin',
        'identity.password_reset',
        'identity.end_company_sessions',
        'identity.session_user',
        'companies.directory_before_context',
        'identity.show_invitation',
        'identity.accept_invitation',
        'tenancy.record_terminal_event.load_terminal',
        'time_zones.list',
        'auth.me_sign_out',
    ];

    expect(CrossCompanyFunctions::identifiers())->toBe($expected);
});
