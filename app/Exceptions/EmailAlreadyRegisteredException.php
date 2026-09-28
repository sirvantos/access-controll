<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EmailAlreadyRegisteredException extends Exception implements ShouldntReport
{
    public function __construct(public readonly string $field = 'email')
    {
        parent::__construct();
    }

    public function render(Request $request): JsonResponse
    {
        $message = __('identity.email_already_registered');

        return response()->json([
            'message' => $message,
            'errors' => [
                $this->field => [$message],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
