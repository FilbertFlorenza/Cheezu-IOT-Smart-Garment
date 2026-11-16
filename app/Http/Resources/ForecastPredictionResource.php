<?php

namespace App\Http\Resources;

use App\Models\ForecastPrediction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ForecastPrediction */
class ForecastPredictionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'metric_name' => $this->metric_name,
            'metric_label' => $this->metricLabel(),
            'metric_unit' => $this->unit(),
            'predicted_value' => $this->predicted_value,
            'actual_value' => $this->actual_value,
            'predicted_at' => $this->predicted_at?->toIso8601String(),
            'anomaly_risk' => $this->anomaly_risk,
            'risk_level' => $this->risk_level?->value,
            'metadata' => $this->metadata,
        ];
    }
}
