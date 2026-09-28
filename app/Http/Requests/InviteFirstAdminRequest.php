<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Identity\Data\InviteUserData;
use App\Modules\Identity\PublicApi\Role;
use App\Support\Validation\EmailRules;
use Illuminate\Foundation\Http\FormRequest;

final class InviteFirstAdminRequest extends FormRequest
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

    public function toDto(): InviteUserData
    {
        return new InviteUserData(
            email: $this->string('email')->lower(),
            role: Role::CompanyAdmin,
        );
    }
}
