<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompanyNotSelectedException extends Exception implements ShouldntReport
{
    public const string ERROR_CODE = 'company_not_selected';

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __('tenancy.company_not_selected'),
            'error_code' => self::ERROR_CODE,
        ], Response::HTTP_CONFLICT);
    }
}
