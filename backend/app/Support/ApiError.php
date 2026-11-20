<?php

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The one error shape used by every API endpoint:
 *
 *   { "error": { "code": "seat_taken", "message": "...", "details": { ... } } }
 *
 * `code` is stable and meant for the app's logic; `message` is for developers
 * and may change. The Android client maps codes to translated UI text.
 */
final class ApiError
{
    public static function response(string $code, string $message, int $status, array $details = [], array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                // Always an object, even when empty, so clients can rely on the type.
                'details' => (object) $details,
            ],
        ], $status, $headers);
    }

    public static function fromThrowable(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ApiException => self::response($e->errorCode, $e->getMessage(), $e->status, $e->details),

            $e instanceof ValidationException => self::response(
                'validation_failed', 'The given data was invalid.', 422, ['fields' => $e->errors()],
            ),

            $e instanceof AuthenticationException => self::response(
                'unauthenticated', 'Missing or invalid access token.', 401,
            ),

            $e instanceof HttpExceptionInterface => self::fromHttpException($e),

            default => self::response(
                'server_error', 'Something went wrong on our side.', 500,
                // Only expose internals on a developer machine.
                config('app.debug') ? ['exception' => $e::class, 'message' => $e->getMessage()] : [],
            ),
        };
    }

    /**
     * Laravel has already converted ModelNotFound -> 404 and authorization
     * failures -> 403 by the time we get here. We use fixed messages instead
     * of the exception text, which can leak class names ("No query results
     * for model [App\Models\Movie]").
     */
    private static function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        [$code, $message] = match ($status) {
            403 => ['forbidden', 'You are not allowed to do this.'],
            404 => ['not_found', 'The requested resource was not found.'],
            405 => ['method_not_allowed', 'This HTTP method is not supported here.'],
            429 => ['too_many_requests', 'Too many requests. Please slow down.'],
            503 => ['service_unavailable', 'The service is temporarily unavailable.'],
            default => ['http_error', 'The request could not be processed.'],
        };

        return self::response($code, $message, $status, headers: $e->getHeaders());
    }
}
