<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ForecastSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\ForecastResource;
use App\Models\Forecast;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function cloud(Request $request): JsonResponse
    {
        return $this->index($request, ForecastSource::Cloud);
    }

    public function network(Request $request): JsonResponse
    {
        return $this->index($request, ForecastSource::Network);
    }

    private function index(Request $request, ForecastSource $source): JsonResponse
    {
        $validated = $request->validate([
            'latest' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($request->boolean('latest')) {
            $forecast = Forecast::latestActiveFor($source);

            return ApiResponse::success(
                $forecast ? (new ForecastResource($forecast))->resolve($request) : null,
            );
        }

        $forecasts = Forecast::query()
            ->forSource($source)
            ->latest('generated_at')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return ApiResponse::paginated($forecasts, ForecastResource::class);
    }
}
