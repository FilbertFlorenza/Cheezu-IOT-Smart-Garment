<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\ForecastPayload;
use App\Enums\ForecastSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCloudForecastRequest;
use App\Http\Requests\Api\V1\StoreForecastRequest;
use App\Http\Requests\Api\V1\StoreNetworkForecastRequest;
use App\Http\Resources\ForecastResource;
use App\Services\ForecastIngestionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ForecastIngestionController extends Controller
{
    public function __construct(private readonly ForecastIngestionService $service) {}

    public function storeCloud(StoreCloudForecastRequest $request): JsonResponse
    {
        return $this->store($request, ForecastSource::Cloud);
    }

    public function storeNetwork(StoreNetworkForecastRequest $request): JsonResponse
    {
        return $this->store($request, ForecastSource::Network);
    }

    private function store(StoreForecastRequest $request, ForecastSource $source): JsonResponse
    {
        $payload = ForecastPayload::fromArray($request->validated());
        $forecast = $this->service->ingest($source, $payload);

        return ApiResponse::success(
            (new ForecastResource($forecast))->resolve($request),
            201,
        );
    }
}
