<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The response shapes of PRD §8.1: success {"message", "data"}, errors {"error": {"code", "message", "details?"}},
 * 204 with an empty body, and "bare" JSON for the older routes that have no envelope.
 */
final class ApiResponse
{
    public const JSON_FLAGS = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION;

    /** @param array<string, string> $headers */
    public static function ok(mixed $data, string $message = 'OK', int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return self::json(['message' => $message, 'data' => $data], $status, $headers);
    }

    public static function created(mixed $data, string $message = 'Created'): JsonResponse
    {
        return self::ok($data, $message, Response::HTTP_CREATED);
    }

    /** 202 for work handed to a job (PRD §5 A1). */
    public static function accepted(mixed $data, string $message = 'Accepted'): JsonResponse
    {
        return self::ok($data, $message, Response::HTTP_ACCEPTED);
    }

    public static function noContent(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** A route that answers without the envelope (marked "Bare" in PRD §8). */
    public static function bare(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return self::json($data, $status);
    }

    /** @param array<string, mixed> $details */
    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ([] !== $details) {
            $error['details'] = $details;
        }

        return self::json(['error' => $error], $status);
    }

    /** @param array<string, string> $headers */
    private static function json(mixed $data, int $status, array $headers = []): JsonResponse
    {
        $response = new JsonResponse(null, $status, $headers);
        $response->setEncodingOptions(self::JSON_FLAGS);
        $response->setData($data);

        return $response;
    }
}
