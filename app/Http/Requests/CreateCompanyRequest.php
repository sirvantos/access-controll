<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Companies\Data\CreateCompanyData;
use App\Modules\Companies\Data\WorkingDaySettingDefaults;
use App\Support\Validation\BinRules;
use App\Support\Validation\EmailRules;
use App\Support\Validation\OptionalEmailRules;
use App\Support\Validation\PhoneRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Stringable;

final class CreateCompanyRequest extends FormRequest
{
    public const int COMPANY_NAME_MAX_LENGTH = 255;

    public const int COMPANY_CONTACT_PERSON_MAX_LENGTH = 255;

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
            'time_zone' => ['required', 'string', 'timezone:all'],
            'bin' => BinRules::rules(),
            'contact_person' => ['nullable', 'string', 'min:1', 'max:'.self::COMPANY_CONTACT_PERSON_MAX_LENGTH],
            'phone' => PhoneRules::rules(),
            'email' => OptionalEmailRules::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bin.regex' => __('companies.bin', ['length' => BinRules::COMPANY_BIN_LENGTH]),
            'phone.regex' => __('companies.phone'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('time_zone')) {
            $this->merge([
                'time_zone' => WorkingDaySettingDefaults::DEFAULT_TIME_ZONE,
            ]);
        }
    }

    public function toDto(): CreateCompanyData
    {
        $email = $this->optionalDetail('email');

        return new CreateCompanyData(
            name: $this->string('name'),
            firstAdminEmail: $this->string('first_admin_email')->lower(),
            timeZone: $this->string('time_zone'),
            bin: $this->optionalDetail('bin'),
            contactPerson: $this->optionalDetail('contact_person'),
            phone: $this->optionalDetail('phone'),
            email: $email?->lower(),
        );
    }

    private function optionalDetail(string $key): ?Stringable
    {
        if (! $this->filled($key)) {
            return null;
        }

        return $this->string($key);
    }
}
