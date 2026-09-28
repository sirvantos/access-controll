<?php

declare(strict_types=1);

namespace App\Modules\Companies\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'bin', 'contact_person', 'phone', 'email', 'name_normalized', 'deactivated_at'])]
#[UseFactory(CompanyFactory::class)]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * @return HasMany<CompanyTimeZoneVersion, $this>
     */
    public function timeZoneVersions(): HasMany
    {
        return $this->hasMany(CompanyTimeZoneVersion::class);
    }

    /**
     * @return HasMany<CompanyWorkingDaySettingVersion, $this>
     */
    public function workingDaySettingVersions(): HasMany
    {
        return $this->hasMany(CompanyWorkingDaySettingVersion::class);
    }

    protected static function booted(): void
    {
        static::saving(fn (Company $company): bool => $company->syncNameNormalized());
    }

    /**
     * @return array{
     *     id: 'integer',
     *     name: 'string',
     *     bin: 'string',
     *     contact_person: 'string',
     *     phone: 'string',
     *     email: 'string',
     *     name_normalized: 'string',
     *     deactivated_at: 'datetime',
     *     created_at: 'datetime',
     *     updated_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'bin' => 'string',
            'contact_person' => 'string',
            'phone' => 'string',
            'email' => 'string',
            'name_normalized' => 'string',
            'deactivated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    private function syncNameNormalized(): bool
    {
        $normalized = mb_strtolower((string) $this->name);

        if ($this->name_normalized === $normalized) {
            return true;
        }

        $this->name_normalized = $normalized;

        return true;
    }
}
