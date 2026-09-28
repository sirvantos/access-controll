<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SignInBlockedException extends Exception implements ShouldntReport
{
    public function render(Request $request): JsonResponse
    {
        $message = __('auth.blocked');

        return response()->json([
            'message' => $message,
            'errors' => [
                'email' => [$message],
            ],
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }
}
