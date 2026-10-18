<?php

namespace App\Exceptions;

use RuntimeException;

/** Domain error rendered with Dub's `{error: {code, message, doc_url}}` envelope. */
class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $message): self
    {
        return new self('bad_request', $message, 400);
    }

    public static function forbidden(string $message): self
    {
        return new self('forbidden', $message, 403);
    }

    public static function notFound(string $message = 'The requested resource was not found.'): self
    {
        return new self('not_found', $message, 404);
    }

    public static function conflict(string $message): self
    {
        return new self('conflict', $message, 409);
    }

    public static function unprocessable(string $message, array $errors = []): self
    {
        return new self('unprocessable_entity', $message, 422, $errors ? ['errors' => $errors] : []);
    }

    public static function limitExceeded(string $message): self
    {
        return new self('exceeded_limit', $message, 403);
    }
}
