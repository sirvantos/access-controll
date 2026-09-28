<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Identity\Data\InviteUserData;
use App\Modules\Identity\PublicApi\Role;
use App\Support\Validation\EmailRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteUserRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
        ];
    }

    public function toDto(): InviteUserData
    {
        return new InviteUserData(
            email: $this->string('email')->lower()->toString(),
            role: Role::from($this->string('role')->toString()),
        );
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(): array
    {
        return array_map(
            fn (Role $role): string => $role->value,
            Role::assignable(),
        );
    }
}
