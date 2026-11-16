<?php

namespace App\Services;

use App\Data\ForecastPayload;
use App\Enums\ForecastSource;
use App\Enums\ForecastStatus;
use App\Exceptions\ApiException;
use App\Models\Forecast;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ForecastIngestionService
{
    /**
     * Persist a forecast and its predictions, superseding the previous active
     * forecast for the same source.
     */
    public function ingest(ForecastSource $source, ForecastPayload $payload): Forecast
    {
        $exists = Forecast::query()
            ->where('source_type', $source)
            ->where('external_id', $payload->externalId)
            ->exists();

        if ($exists) {
            throw ApiException::duplicateForecast($source->value, $payload->externalId);
        }

        try {
            $forecast = DB::transaction(function () use ($source, $payload): Forecast {
                Forecast::query()
                    ->where('source_type', $source)
                    ->where('status', ForecastStatus::Active)
                    ->update(['status' => ForecastStatus::Superseded]);

                $forecast = Forecast::query()->create([
                    'external_id' => $payload->externalId,
                    'source_type' => $source,
                    'model_name' => $payload->modelName,
                    'model_version' => $payload->modelVersion,
                    'forecast_horizon_minutes' => $payload->forecastHorizonMinutes,
                    'generated_at' => $payload->generatedAt,
                    'received_at' => now(),
                    'status' => ForecastStatus::Active,
                    'metadata' => $payload->metadata ?: null,
                ]);

                $forecast->predictions()->createMany(
                    collect($payload->predictions)->map(fn ($prediction) => [
                        'metric_name' => $prediction->metricName,
                        'metric_unit' => $prediction->metricUnit,
                        'predicted_value' => $prediction->predictedValue,
                        'actual_value' => $prediction->actualValue,
                        'predicted_at' => $prediction->predictedAt,
                        'anomaly_risk' => $prediction->anomalyRisk,
                        'risk_level' => $prediction->riskLevel,
                        'metadata' => $prediction->metadata ?: null,
                    ])->all(),
                );

                return $forecast;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof QueryException
                && (($exception->errorInfo[1] ?? null) === 1062 || Str::contains($exception->getMessage(), 'Duplicate entry'))) {
                throw ApiException::duplicateForecast($source->value, $payload->externalId);
            }

            throw $exception;
        }

        Log::info('forecast.ingested', [
            'source' => $source->value,
            'forecast_id' => $forecast->external_id,
            'model' => $forecast->model_name,
            'model_version' => $forecast->model_version,
            'prediction_count' => count($payload->predictions),
        ]);

        return $forecast->load('predictions');
    }
}
