<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $code = 200, array $meta = []): JsonResponse
    {
        $response = $message ? ['message' => $message] : [];
        if ($data !== null) {
            $response['data'] = $data;
        }
        if (! empty($meta)) {
            $response = array_merge($response, $meta);
        }

        return response()->json($response, $code);
    }

    public static function paginated(mixed $data, int $total, int $page, int $limit): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public static function error(string $message, int $code = 400, ?string $codeKey = null, array $extra = []): JsonResponse
    {
        $response = ['error' => $message];
        if ($codeKey) {
            $response['code'] = $codeKey;
        }
        if (! empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return response()->json($response, $code);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return static::success($data, $message, 201);
    }

    public static function deleted(string $message = 'Deleted successfully'): JsonResponse
    {
        return static::success(null, $message);
    }

    public static function ok(array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['ok' => true], $extra));
    }
}
