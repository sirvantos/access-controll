<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EmployeeNumberTakenException extends Exception implements ShouldntReport
{
    public function render(Request $request): JsonResponse
    {
        $message = __('tenancy.employee_number_taken');

        return response()->json([
            'message' => $message,
            'errors' => [
                'employee_number' => [$message],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
