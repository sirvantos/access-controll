<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\PublicApi;

final readonly class CompanyMediaFileView
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $contentType,
    ) {}
}
