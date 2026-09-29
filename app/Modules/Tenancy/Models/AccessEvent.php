<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Support\BelongsToCompany;
use Database\Factories\AccessEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'terminal_id', 'employee_number', 'employee_id', 'snapshot_media_id', 'created_at'])]
#[UseFactory(AccessEventFactory::class)]
class AccessEvent extends Model
{
    /** @use HasFactory<AccessEventFactory> */
    use BelongsToCompany, HasFactory;

    public const ?string UPDATED_AT = null;

    protected static function booted(): void
    {
        static::saving(function (AccessEvent $event): void {
            if ($event->isDirty('terminal_id')) {
                Terminal::query()->whereKey($event->terminal_id)->firstOrFail();
            }

            if ($event->isDirty('snapshot_media_id') && $event->snapshot_media_id !== null) {
                CompanyMedia::query()->whereKey($event->snapshot_media_id)->firstOrFail();
            }

            if ($event->isDirty('employee_id') && $event->employee_id !== null) {
                Employee::query()->whereKey($event->employee_id)->firstOrFail();
            }
        });
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<CompanyMedia, $this>
     */
    public function snapshotMedia(): BelongsTo
    {
        return $this->belongsTo(CompanyMedia::class, 'snapshot_media_id');
    }

    /**
     * @return array{
     *     id: 'integer',
     *     company_id: 'integer',
     *     terminal_id: 'integer',
     *     employee_number: 'string',
     *     employee_id: 'integer',
     *     snapshot_media_id: 'integer',
     *     created_at: 'datetime'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'terminal_id' => 'integer',
            'employee_number' => 'string',
            'employee_id' => 'integer',
            'snapshot_media_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
