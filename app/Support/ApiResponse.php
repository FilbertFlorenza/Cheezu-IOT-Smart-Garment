<?php

namespace App\Support;

use App\Enums\ForecastSource;
use App\Enums\InspectionStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'request_id' => self::requestId(),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource): JsonResponse
    {
        $data = $resource::collection($paginator->items())->resolve(request());

        return self::success($data, 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public static function error(
        string $code,
        string $message,
        int $status,
        array $details = [],
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
            'request_id' => self::requestId(),
        ], $status);
    }

    public static function inspectionStatusLabel(InspectionStatus $status): string
    {
        return $status->label();
    }

    public static function forecastSourceLabel(ForecastSource $source): string
    {
        return $source->label();
    }

    private static function requestId(): ?string
    {
        return request()->attributes->get('request_id');
    }
}
