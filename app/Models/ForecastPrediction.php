<?php

namespace App\Models;

use App\Enums\RiskLevel;
use Database\Factories\ForecastPredictionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastPrediction extends Model
{
    /** @use HasFactory<ForecastPredictionFactory> */
    use HasFactory;

    protected $fillable = [
        'forecast_id',
        'metric_name',
        'metric_unit',
        'predicted_value',
        'actual_value',
        'predicted_at',
        'anomaly_risk',
        'risk_level',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'predicted_value' => 'float',
            'actual_value' => 'float',
            'predicted_at' => 'datetime',
            'anomaly_risk' => 'float',
            'risk_level' => RiskLevel::class,
            'metadata' => 'array',
        ];
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(Forecast::class);
    }

    public function metricLabel(): string
    {
        $configured = config("forecast.metrics.{$this->forecast?->source_type?->metricConfigKey()}.{$this->metric_name}");

        return $configured['label'] ?? str($this->metric_name)->replace('_', ' ')->title()->toString();
    }

    public function unit(): ?string
    {
        return $this->metric_unit
            ?? config("forecast.metrics.{$this->forecast?->source_type?->metricConfigKey()}.{$this->metric_name}.unit");
    }
}
