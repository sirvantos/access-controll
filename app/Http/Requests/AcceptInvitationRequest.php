<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Validation\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

final class AcceptInvitationRequest extends FormRequest
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
            'password' => PasswordRules::rules(),
        ];
    }
}
