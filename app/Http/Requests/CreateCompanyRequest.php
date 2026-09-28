<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Companies\Data\CreateCompanyData;
use App\Support\Validation\EmailRules;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCompanyRequest extends FormRequest
{
    public const int COMPANY_NAME_MAX_LENGTH = 255;

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
            'name' => ['required', 'string', 'min:1', 'max:'.self::COMPANY_NAME_MAX_LENGTH],
            'first_admin_email' => EmailRules::rules(),
        ];
    }

    public function toDto(): CreateCompanyData
    {
        return new CreateCompanyData(
            name: $this->string('name')->toString(),
            firstAdminEmail: $this->string('first_admin_email')->lower()->toString(),
        );
    }
}
