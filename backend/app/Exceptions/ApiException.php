<?php

namespace App\Exceptions;

use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Throw this from actions for expected business errors, e.g.
 *   throw new ApiException('seat_taken', 'Some seats are no longer available.', 409, ['seat_ids' => [12]]);
 * Laravel calls render() automatically and the client gets the standard error shape.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiError::fromThrowable($this);
    }
}
