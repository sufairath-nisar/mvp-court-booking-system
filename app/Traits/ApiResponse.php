<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardised JSON response envelope used across all API endpoints.
 */
trait ApiResponse
{
    /**
     * Return a successful JSON response.
     */
    protected function successResponse(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param array<string, mixed>|null $errors
     */
    protected function errorResponse(string $message = 'Error', int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
