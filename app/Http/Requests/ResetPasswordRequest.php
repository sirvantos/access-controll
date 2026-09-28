<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Identity\Data\PasswordResetData;
use App\Support\Validation\EmailRules;
use App\Support\Validation\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => EmailRules::rules(),
            'password' => PasswordRules::rules(),
        ];
    }

    public function toDto(): PasswordResetData
    {
        return new PasswordResetData(
            email: $this->string('email')->lower()->toString(),
            token: $this->string('token')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
