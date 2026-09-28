<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LastActiveAdminException extends Exception implements ShouldntReport
{
    public const string ERROR_CODE = 'last_active_admin';

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __('identity.last_active_admin'),
            'error_code' => self::ERROR_CODE,
        ], Response::HTTP_CONFLICT);
    }
}
