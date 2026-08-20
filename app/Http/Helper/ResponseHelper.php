<?php

namespace App\Http\Helper;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResponseHelper
{
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            $payload = array_merge(
                ['message' => $message],
                self::paginatorPayload($data, $meta)
            );

            return response()->json($payload, $status);
        }

        $payload = [
            'message' => $message,
            'data' => self::normalizeData($data),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function data(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            return response()->json(self::paginatorPayload($data, $meta), $status);
        }

        $payload = ['data' => self::normalizeData($data)];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    public static function payload(mixed $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function formatPagination(LengthAwarePaginator $result): JsonResponse
    {
        $response = [
            'status'        => true,
            'code'          => 200,
            'message'       => "Success",
            'total'         => $result->total(),
            'perPage'       => $result->perPage(),
            'currentPage'   => $result->currentPage(),
            'data'          => $result->items(),
        ];

        return response()->json($response, 200);
    }

    public static function successResponse(mixed $data, string $msg = 'Success', int $code = 200): JsonResponse
    {
        $response = [
            'status'        => true,
            'code'          => $code,
            'message'       => $msg,
            'data'          => $data,
        ];
        return response()->json($response, 200);
    }

    public static function failedResponse(mixed $data, string $msg = 'Failed', int $code = 400, int $line = 0, string $filePath = ''): JsonResponse
    {
        $response = [
            'status'        => false,
            'code'          => $code,
            'message'       => $msg,
            'line'          => $line,
            'file'          => $filePath,
            'data'          => $data,
        ];
        return response()->json($response, 400);
    }

    public static function unauthorizedResponse(mixed $data, string $msg = 'Unauthorized', int $code = 401): JsonResponse
    {
        $response = [
            'status'        => false,
            'code'          => $code,
            'message'       => $msg,
            'data'          => $data,
        ];
        return response()->json($response, $code);
    }

    private static function paginatorPayload(LengthAwarePaginator $result, array $meta = []): array
    {
        return [
            'data' => self::normalizeData($result->items()),
            'meta' => array_merge($meta, [
                'total' => $result->total(),
                'perPage' => $result->perPage(),
                'currentPage' => $result->currentPage(),
            ]),
        ];
    }

    private static function normalizeData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        if ($data instanceof Model) {
            return self::resourceFor($data)?->resolve(request()) ?? $data;
        }

        if ($data instanceof Collection) {
            return $data->map(fn (mixed $item) => self::normalizeData($item))->values();
        }

        if (is_array($data)) {
            return array_map(fn (mixed $item) => self::normalizeData($item), $data);
        }

        return $data;
    }

    private static function resourceFor(Model $model): ?JsonResource
    {
        $resourceClass = 'App\\Model\\Response\\'.Str::afterLast($model::class, '\\').'Resource';

        if (! class_exists($resourceClass)) {
            return null;
        }

        return new $resourceClass($model);
    }
}
