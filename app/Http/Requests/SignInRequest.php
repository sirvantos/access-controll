<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Identity\Data\SignInAttempt;
use App\Support\Validation\EmailRules;
use Illuminate\Foundation\Http\FormRequest;

final class SignInRequest extends FormRequest
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
            'email' => EmailRules::rules(),
            'password' => ['required', 'string'],
        ];
    }

    public function toDto(): SignInAttempt
    {
        return new SignInAttempt(
            email: $this->string('email')->lower(),
            password: $this->string('password'),
            ip: (string) $this->ip(),
        );
    }
}
