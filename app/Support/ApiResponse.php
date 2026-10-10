<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    public static function data(
        mixed $data,
        int $status = 200,
        string $message = 'Request completed successfully.',
        ?array $meta = null,
        ?array $links = null,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        if ($links !== null) {
            $payload['links'] = $links;
        }

        return response()->json($payload, $status);
    }

    public static function success(string $message, int $status = 200): JsonResponse
    {
        // Keep data.message for clients that consumed the previous mutation response.
        return self::data(['message' => $message], $status, $message);
    }

    public static function error(
        string $message,
        string $code,
        int $status,
        ?array $errors = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        mixed $data,
        string $message = 'Request completed successfully.',
    ): JsonResponse {
        return self::data(
            $data,
            200,
            $message,
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        );
    }
}
