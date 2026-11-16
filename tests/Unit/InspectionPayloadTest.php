<?php

use App\Data\InspectionPayload;

function payloadData(array $overrides = []): array
{
    return array_merge([
        'inspection_id' => '00000000-0000-4000-8000-000000000001',
        'device_code' => 'DEVICE-001',
        'inspected_at' => '2026-01-01T08:00:00Z',
        'overall_status' => 'PASS',
        'defect_count' => 0,
        'processing_time_ms' => 500,
        'model_version' => 'v1.0',
        'detections' => [],
    ], $overrides);
}

test('a zero-detection pass is consistent', function () {
    expect(InspectionPayload::fromArray(payloadData())->isConsistent())->toBeTrue();
});

test('a submission with detections and a defect status is consistent', function () {
    $payload = InspectionPayload::fromArray(payloadData([
        'overall_status' => 'DEFECT',
        'defect_count' => 2,
        'detections' => [
            ['defect_class' => 'hole', 'confidence' => 0.9],
            ['defect_class' => 'stain', 'confidence' => 0.6],
        ],
    ]));

    expect($payload->isConsistent())->toBeTrue()
        ->and($payload->detections)->toHaveCount(2)
        ->and($payload->detections[0]->defectClass)->toBe('hole');
});

test('a defect status with no detections is inconsistent', function () {
    $payload = InspectionPayload::fromArray(payloadData([
        'overall_status' => 'DEFECT',
        'defect_count' => 0,
    ]));

    expect($payload->isConsistent())->toBeFalse();
});

test('a defect count that does not match the detections is inconsistent', function () {
    $payload = InspectionPayload::fromArray(payloadData([
        'overall_status' => 'DEFECT',
        'defect_count' => 3,
        'detections' => [['defect_class' => 'hole', 'confidence' => 0.9]],
    ]));

    expect($payload->isConsistent())->toBeFalse();
});

test('detections normalise bounding boxes and indexes', function () {
    $payload = InspectionPayload::fromArray(payloadData([
        'overall_status' => 'DEFECT',
        'defect_count' => 1,
        'detections' => [[
            'defect_class' => 'tear',
            'confidence' => 0.75,
            'bounding_box' => ['x' => 5, 'y' => 6, 'width' => 7, 'height' => 8],
        ]],
    ]));

    $detection = $payload->detections[0];

    expect($detection->index)->toBe(0)
        ->and($detection->bboxX)->toBe(5)
        ->and($detection->bboxWidth)->toBe(7);
});
