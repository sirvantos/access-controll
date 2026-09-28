<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Validation\EmailRules;
use Illuminate\Foundation\Http\FormRequest;

final class EmailRequest extends FormRequest
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
        ];
    }

    public function emailAddress(): string
    {
        return $this->string('email')->lower()->toString();
    }
}
