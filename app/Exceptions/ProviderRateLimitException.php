<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the notification provider returns 429 (rate limit).
 * The job should release back to the queue after retryAfter seconds.
 */
class ProviderRateLimitException extends Exception
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        string $message = 'Provider rate limit (429)',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
