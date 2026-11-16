<?php

namespace App\Data;

use App\Enums\InspectionStatus;
use Carbon\CarbonImmutable;

final readonly class InspectionPayload
{
    /**
     * @param  array<int, DetectionData>  $detections
     */
    public function __construct(
        public string $inspectionId,
        public string $deviceCode,
        public CarbonImmutable $inspectedAt,
        public InspectionStatus $overallStatus,
        public int $defectCount,
        public ?int $processingTimeMs,
        public string $modelVersion,
        public array $detections = [],
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            inspectionId: (string) $data['inspection_id'],
            deviceCode: (string) $data['device_code'],
            inspectedAt: CarbonImmutable::parse($data['inspected_at'])->utc(),
            overallStatus: InspectionStatus::from($data['overall_status']),
            defectCount: (int) $data['defect_count'],
            processingTimeMs: isset($data['processing_time_ms']) ? (int) $data['processing_time_ms'] : null,
            modelVersion: (string) $data['model_version'],
            detections: collect($data['detections'] ?? [])
                ->values()
                ->map(fn (array $detection, int $index) => DetectionData::fromArray($detection, $index))
                ->all(),
            metadata: $data['metadata'] ?? [],
        );
    }

    public function isConsistent(): bool
    {
        $expectedStatus = $this->defectCount > 0 ? InspectionStatus::Defect : InspectionStatus::Pass;

        return $this->defectCount === count($this->detections)
            && $this->overallStatus === $expectedStatus;
    }
}
