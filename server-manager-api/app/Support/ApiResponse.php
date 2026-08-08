<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

/**
 * Single source of truth for the API envelope.
 *
 * Every endpoint answers with the same shape:
 *
 *   { "success": bool, "message": string, "data": mixed }
 *
 * List endpoints additionally carry `meta` (pagination) and error responses
 * carry `errors` (field => messages).
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public static function success(
        mixed $data = null,
        string $message = '',
        int $status = Response::HTTP_OK,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status, $headers);
    }

    /**
     * 201 for freshly created records.
     */
    public static function created(mixed $data = null, string $message = 'Created successfully.'): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Successful mutation with nothing meaningful to return.
     */
    public static function deleted(string $message = 'Deleted successfully.'): JsonResponse
    {
        return self::success(null, $message);
    }

    /**
     * Wrap a paginator, flattening Laravel's nested pagination payload into a
     * predictable `data` + `meta` pair the React client consumes directly.
     *
     * Accepts either a raw paginator or an API-Resource collection built from
     * one, so callers keep using Resources for field shaping.
     */
    public static function paginated(
        LengthAwarePaginator|ResourceCollection $paginator,
        string $message = '',
    ): JsonResponse {
        if ($paginator instanceof ResourceCollection) {
            /** @var LengthAwarePaginator $underlying */
            $underlying = $paginator->resource;
            $items = $paginator->collection;
        } else {
            $underlying = $paginator;
            $items = $paginator->items();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $underlying->currentPage(),
                'per_page' => $underlying->perPage(),
                'last_page' => $underlying->lastPage(),
                'total' => $underlying->total(),
                'from' => $underlying->firstItem(),
                'to' => $underlying->lastItem(),
            ],
        ]);
    }

    /**
     * A single resource, unwrapped from JsonResource's default `data` nesting
     * so the envelope is never doubled.
     */
    public static function resource(
        JsonResource $resource,
        string $message = '',
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        return self::success($resource, $message, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $errors = [],
        mixed $data = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validationError(
        array $errors,
        string $message = 'The given data was invalid.',
    ): JsonResponse {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'This action is unauthorized.'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }
}
