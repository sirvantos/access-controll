<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use App\Support\BelongsToCompany;
use Database\Factories\CompanyMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'public_id', 'kind', 'disk', 'path', 'content_type'])]
#[UseFactory(CompanyMediaFactory::class)]
class CompanyMedia extends Model
{
    /** @use HasFactory<CompanyMediaFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array{
     *     id: 'integer',
     *     company_id: 'integer',
     *     public_id: 'string',
     *     kind: 'App\Modules\Tenancy\PublicApi\CompanyMediaKind',
     *     disk: 'string',
     *     path: 'string',
     *     content_type: 'string',
     *     created_at: 'datetime',
     *     updated_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'public_id' => 'string',
            'kind' => CompanyMediaKind::class,
            'disk' => 'string',
            'path' => 'string',
            'content_type' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
