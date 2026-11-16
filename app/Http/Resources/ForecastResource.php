<?php

namespace App\Http\Resources;

use App\Models\Forecast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Forecast */
class ForecastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'forecast_id' => $this->external_id,
            'source' => $this->source_type?->value,
            'model_name' => $this->model_name,
            'model_version' => $this->model_version,
            'forecast_horizon_minutes' => $this->forecast_horizon_minutes,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'status' => $this->status?->value,
            'metadata' => $this->metadata,
            'predictions' => ForecastPredictionResource::collection($this->whenLoaded('predictions')),
            'warning_count' => $this->when(
                $this->relationLoaded('predictions'),
                fn () => $this->warningCount(),
            ),
        ];
    }
}
