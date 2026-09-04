<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class ApiResponse
{
    /**
     * Return a successful API response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Request successful.',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a resource-created API response.
     */
    public static function created(
        mixed $data = null,
        string $message = 'Resource created successfully.',
    ): JsonResponse {
        return self::success($data, $message, 201);
    }

    /**
     * Return a successful response with no content.
     */
    public static function noContent(
        string $message = 'Request completed successfully.',
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => null,
        ], 200);
    }

    /**
     * Return a failed API response.
     */
    public static function error(
        string $message = 'Request failed.',
        int $status = 400,
        mixed $errors = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return a validation-error response.
     */
    public static function validationError(
        mixed $errors,
        string $message = 'Validation failed.',
    ): JsonResponse {
        return self::error($message, 422, $errors);
    }

    /**
     * Return an unauthorized response.
     */
    public static function unauthorized(
        string $message = 'Unauthenticated.',
    ): JsonResponse {
        return self::error($message, 401);
    }

    /**
     * Return a forbidden response.
     */
    public static function forbidden(
        string $message = 'You are not authorized to perform this action.',
    ): JsonResponse {
        return self::error($message, 403);
    }

    /**
     * Return a not-found response.
     */
    public static function notFound(
        string $message = 'Resource not found.',
    ): JsonResponse {
        return self::error($message, 404);
    }

    /**
     * Return a conflict response.
     */
    public static function conflict(
        string $message = 'The request conflicts with the current state.',
        mixed $errors = null,
    ): JsonResponse {
        return self::error($message, 409, $errors);
    }

    /**
     * Return a too-many-requests response.
     */
    public static function tooManyRequests(
        string $message = 'Too many requests.',
        mixed $errors = null,
    ): JsonResponse {
        return self::error($message, 429, $errors);
    }

    /**
     * Return a server-error response.
     */
    public static function serverError(
        string $message = 'An unexpected server error occurred.',
        mixed $errors = null,
    ): JsonResponse {
        return self::error($message, 500, $errors);
    }

    /**
     * Return a successful paginated API response.
     */
    public static function paginated(
        ResourceCollection $resource,
        string $resourceKey,
        string $message = 'Request successful.',
    ): JsonResponse {
        $resourceResponse = $resource->response();

        $payload = $resourceResponse->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                $resourceKey => $payload['data'] ?? [],
            ],
            'meta' => $payload['meta'] ?? [],
            'links' => $payload['links'] ?? [],
        ], $resourceResponse->getStatusCode());
    }
}
