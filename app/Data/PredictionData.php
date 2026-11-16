<?php

namespace App\Data;

use App\Enums\RiskLevel;
use Carbon\CarbonImmutable;

final readonly class PredictionData
{
    public function __construct(
        public string $metricName,
        public ?string $metricUnit,
        public float $predictedValue,
        public ?float $actualValue,
        public CarbonImmutable $predictedAt,
        public ?float $anomalyRisk = null,
        public ?RiskLevel $riskLevel = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $prediction): self
    {
        return new self(
            metricName: (string) $prediction['metric_name'],
            metricUnit: $prediction['metric_unit'] ?? null,
            predictedValue: (float) $prediction['predicted_value'],
            actualValue: isset($prediction['actual_value']) ? (float) $prediction['actual_value'] : null,
            predictedAt: CarbonImmutable::parse($prediction['predicted_at'])->utc(),
            anomalyRisk: isset($prediction['anomaly_risk']) ? (float) $prediction['anomaly_risk'] : null,
            riskLevel: isset($prediction['risk_level']) ? RiskLevel::from($prediction['risk_level']) : null,
            metadata: $prediction['metadata'] ?? [],
        );
    }
}
