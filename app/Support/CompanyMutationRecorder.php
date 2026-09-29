<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

interface CompanyMutationRecorder
{
    public function record(Model $model): void;
}
