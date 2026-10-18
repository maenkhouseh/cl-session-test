<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Maps every exception thrown inside `/api/*` to the shared error envelope. */
class ApiExceptionRenderer
{
    private const DOCS = 'https://shortie.example/docs/api-reference/errors';

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*', 'r/*') && ! $request->expectsJson()) {
            return null;
        }

        [$code, $message, $status, $extra] = match (true) {
            $e instanceof ApiException => [$e->errorCode, $e->getMessage(), $e->status, $e->extra],
            $e instanceof ValidationException => ['unprocessable_entity', $e->validator->errors()->first(), 422, ['errors' => $e->errors()]],
            $e instanceof AuthenticationException => ['unauthorized', 'Unauthenticated.', 401, []],
            $e instanceof AuthorizationException => ['forbidden', $e->getMessage() ?: 'You are not allowed to perform this action.', 403, []],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ['not_found', 'The requested resource was not found.', 404, []],
            $e instanceof ThrottleRequestsException => ['rate_limit_exceeded', 'Too many requests.', 429, []],
            $e instanceof HttpExceptionInterface => [$this->codeForStatus($e->getStatusCode()), $e->getMessage() ?: 'Request failed.', $e->getStatusCode(), []],
            default => ['internal_server_error', config('app.debug') ? $e->getMessage() : 'Something went wrong.', 500, []],
        };

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'doc_url' => self::DOCS.'#'.str_replace('_', '-', $code),
                ...$extra,
            ],
        ], $status, $e instanceof HttpExceptionInterface ? $e->getHeaders() : []);
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request', 401 => 'unauthorized', 403 => 'forbidden', 404 => 'not_found',
            409 => 'conflict', 410 => 'invite_expired', 422 => 'unprocessable_entity', 429 => 'rate_limit_exceeded',
            default => 'internal_server_error',
        };
    }
}
