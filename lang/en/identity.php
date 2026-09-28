<?php

declare(strict_types=1);

return [
    'email_already_registered' => 'This email address is already registered.',
    'invitation_invalid' => 'This invitation is no longer valid.',
    'invitation_not_pending' => 'This invitation is not pending.',
    'last_active_admin' => 'The company must keep at least one active admin.',
    'super_admin_created' => 'Created a super admin for :email.',
    'password' => 'Password',
    'password_confirmation' => 'Confirm password',
    'roles' => [
        'company_admin' => 'Company admin',
        'viewer' => 'Viewer',
    ],
    'invitation' => [
        'subject' => 'You are invited',
        'intro' => 'You are invited to join as :role.',
        'action' => 'Accept invitation',
        'expires' => 'This invitation expires at :expires_at.',
    ],
    'password_reset' => [
        'subject' => 'Reset your password',
        'intro' => 'Use the link below to choose a new password.',
        'action' => 'Reset password',
    ],
];
