<?php

declare(strict_types=1);

namespace Tests\Support\Tenancy;

use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class IsolationProbe extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'isolation_probes';

    /**
     * @var list<string>
     */
    protected $fillable = ['company_id', 'name'];

    public static function ensureSchema(): void
    {
        Schema::dropIfExists('isolation_probes');

        Schema::create('isolation_probes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
        });
    }

    /**
     * @return array{
     *     id: 'integer',
     *     company_id: 'integer',
     *     name: 'string'
     * }
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'name' => 'string',
        ];
    }
}
