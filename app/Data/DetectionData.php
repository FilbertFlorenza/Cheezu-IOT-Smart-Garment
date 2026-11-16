<?php

namespace App\Data;

final readonly class DetectionData
{
    public function __construct(
        public string $defectClass,
        public float $confidence,
        public int $index,
        public ?int $bboxX = null,
        public ?int $bboxY = null,
        public ?int $bboxWidth = null,
        public ?int $bboxHeight = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $detection, int $index): self
    {
        $box = $detection['bounding_box'] ?? null;

        return new self(
            defectClass: (string) $detection['defect_class'],
            confidence: (float) $detection['confidence'],
            index: $index,
            bboxX: isset($box['x']) ? (int) $box['x'] : null,
            bboxY: isset($box['y']) ? (int) $box['y'] : null,
            bboxWidth: isset($box['width']) ? (int) $box['width'] : null,
            bboxHeight: isset($box['height']) ? (int) $box['height'] : null,
            metadata: $detection['metadata'] ?? [],
        );
    }
}
