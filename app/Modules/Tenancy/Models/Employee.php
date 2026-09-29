<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\BelongsToCompany;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'employee_number', 'name', 'photo_media_id'])]
#[UseFactory(EmployeeFactory::class)]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCompany, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            if (! $employee->isDirty('photo_media_id') || $employee->photo_media_id === null) {
                return;
            }

            CompanyMedia::query()->whereKey($employee->photo_media_id)->firstOrFail();
        });
    }

    /**
     * @return BelongsTo<CompanyMedia, $this>
     */
    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(CompanyMedia::class, 'photo_media_id');
    }

    /**
     * @return HasMany<AccessEvent, $this>
     */
    public function accessEvents(): HasMany
    {
        return $this->hasMany(AccessEvent::class);
    }

    /**
     * @return array{
     *     id: 'integer',
     *     company_id: 'integer',
     *     employee_number: 'string',
     *     name: 'string',
     *     photo_media_id: 'integer',
     *     created_at: 'datetime',
     *     updated_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'employee_number' => 'string',
            'name' => 'string',
            'photo_media_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
