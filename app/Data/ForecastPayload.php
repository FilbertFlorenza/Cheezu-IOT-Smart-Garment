<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ForecastPayload
{
    /**
     * @param  array<int, PredictionData>  $predictions
     */
    public function __construct(
        public string $externalId,
        public string $modelName,
        public string $modelVersion,
        public int $forecastHorizonMinutes,
        public CarbonImmutable $generatedAt,
        public array $predictions,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) $data['forecast_id'],
            modelName: (string) $data['model_name'],
            modelVersion: (string) $data['model_version'],
            forecastHorizonMinutes: (int) $data['forecast_horizon_minutes'],
            generatedAt: CarbonImmutable::parse($data['generated_at'])->utc(),
            predictions: collect($data['predictions'])
                ->map(fn (array $prediction) => PredictionData::fromArray($prediction))
                ->all(),
            metadata: $data['metadata'] ?? [],
        );
    }
}
