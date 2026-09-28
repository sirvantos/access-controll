<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Identity\PublicApi\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeRoleRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
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
