<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use App\Modules\Companies\PublicApi\WeekDay;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'start_time',
    'end_time',
    'working_days',
    'break_duration_minutes',
    'break_deducted',
    'lateness_grace_minutes',
    'applies_from',
])]
class CompanyWorkingDaySettingVersion extends Model
{
    use BelongsToCompany;

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
     *     start_time: 'string',
     *     end_time: 'string',
     *     working_days: string,
     *     break_duration_minutes: 'integer',
     *     break_deducted: 'boolean',
     *     lateness_grace_minutes: 'integer',
     *     applies_from: 'datetime',
     *     created_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'start_time' => 'string',
            'end_time' => 'string',
            'working_days' => AsEnumCollection::of(WeekDay::class),
            'break_duration_minutes' => 'integer',
            'break_deducted' => 'boolean',
            'lateness_grace_minutes' => 'integer',
            'applies_from' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
