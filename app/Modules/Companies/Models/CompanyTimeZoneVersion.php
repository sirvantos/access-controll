<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'time_zone', 'applies_from'])]
class CompanyTimeZoneVersion extends Model
{
    public const ?string UPDATED_AT = null;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return array{
     *     id: 'integer',
     *     company_id: 'integer',
     *     time_zone: 'string',
     *     applies_from: 'datetime',
     *     created_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'time_zone' => 'string',
            'applies_from' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
