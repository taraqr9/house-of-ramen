<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * JSON error envelope for /api/* - {"success": false, "message": "...",
 * "errors"?: {...}}. Messages are fixed per status so internal exception
 * text never leaks; PosException (business rules, 422) renders itself with
 * its own safe message before this runs.
 */
class ApiErrorResponse
{
    public static function from(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return self::json(422, 'Validation failed.', ['errors' => $e->errors()]);
        }

        if ($e instanceof AuthenticationException) {
            return self::json(401, 'Unauthenticated.');
        }

        if ($e instanceof AuthorizationException) {
            return self::json(403, 'You do not have permission to perform this action.');
        }

        if ($e instanceof ModelNotFoundException) {
            return self::json(404, 'Resource not found.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            $message = match ($status) {
                403 => 'You do not have permission to perform this action.',
                404 => 'Resource not found.',
                405 => 'Method not allowed.',
                419 => 'Session expired.',
                429 => 'Too many requests. Please slow down and try again shortly.',
                default => $status < 500 ? 'Request could not be processed.' : 'Something went wrong.',
            };

            return self::json($status, $message)->withHeaders(array_intersect_key(
                $e->getHeaders(),
                array_flip(['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'])
            ));
        }

        return self::json(500, 'Something went wrong.');
    }

    private static function json(int $status, string $message, array $extra = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message] + $extra, $status);
    }
}
