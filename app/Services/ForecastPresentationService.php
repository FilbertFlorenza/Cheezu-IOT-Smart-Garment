<?php

namespace App\Services;

use App\Enums\ForecastSource;
use App\Models\Forecast;
use App\Models\ForecastPrediction;
use Illuminate\Support\Collection;

class ForecastPresentationService
{
    /**
     * Build chart-ready series using only stored predictions.
     *
     * @return array<int, array{
     *     metric: string,
     *     label: string,
     *     unit: string|null,
     *     color: string,
     *     labels: array<int, string>,
     *     predicted: array<int, float|null>,
     *     actual: array<int, float|null>,
     *     has_actual: bool,
     *     latest_predicted: float|null,
     *     latest_actual: float|null,
     *     latest_risk: string|null
     * }>
     */
    public function series(Forecast $forecast): array
    {
        $sourceKey = $forecast->source_type->metricConfigKey();
        $configured = config("forecast.metrics.{$sourceKey}", []);
        $grouped = $forecast->predictions->groupBy('metric_name');

        $orderedMetrics = array_values(array_unique(array_merge(
            array_keys($configured),
            $grouped->keys()->all(),
        )));

        $series = [];

        foreach ($orderedMetrics as $metric) {
            /** @var Collection<int, ForecastPrediction>|null $predictions */
            $predictions = $grouped->get($metric);

            if ($predictions === null || $predictions->isEmpty()) {
                continue;
            }

            $predictions = $predictions->sortBy('predicted_at')->values();
            $latest = $predictions->last();
            $hasActual = $predictions->contains(fn (ForecastPrediction $prediction) => $prediction->actual_value !== null);

            $series[] = [
                'metric' => $metric,
                'label' => $configured[$metric]['label'] ?? metric_label($metric, $sourceKey),
                'unit' => $latest->unit(),
                'color' => $configured[$metric]['color'] ?? metric_color($metric, $sourceKey),
                'labels' => $predictions
                    ->map(fn (ForecastPrediction $prediction) => display_datetime($prediction->predicted_at, 'M j, H:i'))
                    ->all(),
                'predicted' => $predictions->map(fn (ForecastPrediction $prediction) => $prediction->predicted_value)->all(),
                'actual' => $predictions->map(fn (ForecastPrediction $prediction) => $prediction->actual_value)->all(),
                'has_actual' => $hasActual,
                'latest_predicted' => $latest->predicted_value,
                'latest_actual' => $latest->actual_value,
                'latest_risk' => $latest->risk_level?->value,
            ];
        }

        return $series;
    }

    /**
     * Metrics that carry a High/Critical risk in the supplied forecast.
     *
     * @return Collection<int, ForecastPrediction>
     */
    public function warnings(Forecast $forecast): Collection
    {
        return $forecast->predictions
            ->filter(fn (ForecastPrediction $prediction) => $prediction->risk_level?->isWarning())
            ->sortByDesc(fn (ForecastPrediction $prediction) => $prediction->risk_level?->value === 'CRITICAL' ? 1 : 0)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function availableSources(): array
    {
        return array_map(fn (ForecastSource $source) => $source->value, ForecastSource::cases());
    }
}
