<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Middleware\ResolveCompanyMediaKind;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCompanyMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof Actor) {
            return false;
        }

        $role = $actor->actorRole();

        if ($role === Role::CompanyAdmin || $role === Role::SuperAdmin) {
            return true;
        }

        $kind = $this->attributes->get(ResolveCompanyMediaKind::KIND_ATTRIBUTE);

        if (! $kind instanceof CompanyMediaKind) {
            return true;
        }

        return in_array($kind, [
            CompanyMediaKind::EventSnapshot,
            CompanyMediaKind::Report,
            CompanyMediaKind::Export,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
