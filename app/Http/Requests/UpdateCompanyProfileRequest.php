<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Companies\Data\UpdateCompanyProfileData;
use App\Support\Validation\BinRules;
use App\Support\Validation\OptionalEmailRules;
use App\Support\Validation\PhoneRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Stringable;
use Spatie\LaravelData\Optional;

final class UpdateCompanyProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:'.CreateCompanyRequest::COMPANY_NAME_MAX_LENGTH],
            'time_zone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'bin' => ['sometimes', ...BinRules::rules()],
            'contact_person' => ['sometimes', 'nullable', 'string', 'min:1', 'max:'.CreateCompanyRequest::COMPANY_CONTACT_PERSON_MAX_LENGTH],
            'phone' => ['sometimes', ...PhoneRules::rules()],
            'email' => ['sometimes', ...OptionalEmailRules::rules()],
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

    public function toDto(): UpdateCompanyProfileData
    {
        return new UpdateCompanyProfileData(
            name: $this->providedString('name'),
            timeZone: $this->providedString('time_zone'),
            bin: $this->optionalDetail('bin'),
            contactPerson: $this->optionalDetail('contact_person'),
            phone: $this->optionalDetail('phone'),
            email: $this->optionalDetail('email'),
        );
    }

    private function providedString(string $key): Optional|Stringable
    {
        if (! $this->exists($key)) {
            return Optional::create();
        }

        return $this->string($key);
    }

    private function optionalDetail(string $key): Optional|Stringable|null
    {
        if (! $this->exists($key)) {
            return Optional::create();
        }

        if (! $this->filled($key)) {
            return null;
        }

        $value = $this->string($key);

        return $key === 'email' ? $value->lower() : $value;
    }
}
