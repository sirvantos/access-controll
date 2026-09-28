<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Exceptions\EmailAlreadyRegisteredException;
use App\Modules\Identity\Actions\CreateSuperAdminAction;
use App\Support\Validation\EmailRules;
use App\Support\Validation\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

final class CreateSuperAdminCommand extends Command
{
    protected $signature = 'identity:create-super-admin {email}';

    protected $description = 'Create a super admin.';

    public function handle(CreateSuperAdminAction $action): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $emailError = $this->firstError(['email' => $email], ['email' => EmailRules::rules()]);

        if ($emailError !== null) {
            $this->error($emailError);

            return self::FAILURE;
        }

        $password = $this->secret(__('identity.password'));
        $confirmation = $this->secret(__('identity.password_confirmation'));
        $passwordValue = is_string($password) ? $password : '';
        $confirmationValue = is_string($confirmation) ? $confirmation : '';
        $passwordError = $this->firstError(
            [
                'password' => $passwordValue,
                'password_confirmation' => $confirmationValue,
            ],
            [
                'password' => PasswordRules::rules().'|confirmed',
            ],
        );

        if ($passwordError !== null) {
            $this->error($passwordError);

            return self::FAILURE;
        }

        try {
            $action($email, $passwordValue);
        } catch (EmailAlreadyRegisteredException) {
            $this->error(__('identity.email_already_registered'));

            return self::FAILURE;
        }

        $this->info(__('identity.super_admin_created', ['email' => $email]));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, string>  $rules
     */
    private function firstError(array $data, array $rules): ?string
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return (string) $validator->errors()->first();
        }

        return null;
    }
}
