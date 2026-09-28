<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InvitationNoLongerValidException extends Exception implements ShouldntReport
{
    public const string ERROR_CODE = 'invitation_invalid';

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __('identity.invitation_invalid'),
            'error_code' => self::ERROR_CODE,
        ], Response::HTTP_GONE);
    }
}
