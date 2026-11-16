<?php

use App\Models\Device;
use App\Models\Inspection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->device = Device::factory()->create(['device_code' => 'DEVICE-TEST']);
    [, $this->token] = serviceToken(['inspections:write', 'read']);
});

function inspectionPayload(array $overrides = []): array
{
    return array_merge([
        'inspection_id' => (string) Str::uuid(),
        'device_code' => 'DEVICE-TEST',
        'inspected_at' => now()->subMinute()->toIso8601String(),
        'overall_status' => 'PASS',
        'defect_count' => 0,
        'processing_time_ms' => 640,
        'model_version' => 'v1.1',
        'metadata' => ['batch' => 'B-1'],
        'detections' => [],
    ], $overrides);
}

test('an inspection with multiple detections is stored with all detections', function () {
    $payload = inspectionPayload([
        'overall_status' => 'DEFECT',
        'defect_count' => 3,
        'detections' => [
            ['defect_class' => 'hole', 'confidence' => 0.91, 'bounding_box' => ['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40]],
            ['defect_class' => 'stain', 'confidence' => 0.72],
            ['defect_class' => 'tear', 'confidence' => 0.65, 'metadata' => ['source' => 'model']],
        ],
    ]);

    $response = $this->postJson('/api/v1/inspections/results', $payload, bearer($this->token));

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.overall_status', 'DEFECT')
        ->assertJsonPath('data.defect_count', 3)
        ->assertJsonCount(3, 'data.detections')
        ->assertJsonPath('data.detections.0.bounding_box.x', 10)
        ->assertJsonPath('meta.duplicate', false);

    $inspection = Inspection::query()->where('inspection_id', $payload['inspection_id'])->firstOrFail();

    expect($inspection->detections)->toHaveCount(3)
        ->and($inspection->detections->pluck('defect_class')->all())->toBe(['hole', 'stain', 'tear'])
        ->and($this->device->fresh()->last_seen_at)->not->toBeNull();
});

test('an inspection with zero detections is accepted as a pass', function () {
    $payload = inspectionPayload();

    $this->postJson('/api/v1/inspections/results', $payload, bearer($this->token))
        ->assertCreated()
        ->assertJsonPath('data.overall_status', 'PASS')
        ->assertJsonPath('data.defect_count', 0)
        ->assertJsonCount(0, 'data.detections');

    expect(Inspection::query()->count())->toBe(1);
});

test('a duplicate inspection id is rejected without overwriting the original', function () {
    $payload = inspectionPayload();

    $this->postJson('/api/v1/inspections/results', $payload, bearer($this->token))->assertCreated();

    $this->postJson('/api/v1/inspections/results', array_merge($payload, [
        'model_version' => 'v2.0',
    ]), bearer($this->token))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'DUPLICATE_INSPECTION');

    expect(Inspection::query()->count())->toBe(1)
        ->and(Inspection::query()->first()->model_version)->toBe('v1.1');
});

test('an unknown device is rejected', function () {
    $this->postJson('/api/v1/inspections/results', inspectionPayload([
        'device_code' => 'MISSING-1',
    ]), bearer($this->token))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'UNKNOWN_DEVICE');
});

test('defect_count and status must match the submitted detections', function () {
    $this->postJson('/api/v1/inspections/results', inspectionPayload([
        'defect_count' => 2,
        'overall_status' => 'DEFECT',
    ]), bearer($this->token))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.defect_count.0', 'defect_count (2) must equal the number of submitted detections (0).');
});

test('a pass status with detections is rejected', function () {
    $this->postJson('/api/v1/inspections/results', inspectionPayload([
        'overall_status' => 'PASS',
        'defect_count' => 1,
        'detections' => [['defect_class' => 'hole', 'confidence' => 0.8]],
    ]), bearer($this->token))->assertStatus(422);
});

test('required fields are validated', function () {
    $this->postJson('/api/v1/inspections/results', [
        'inspection_id' => 'not-a-uuid',
    ], bearer($this->token))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['inspection_id', 'device_code']]]);
});

test('an inspection in the future beyond tolerance is rejected', function () {
    $this->postJson('/api/v1/inspections/results', inspectionPayload([
        'inspected_at' => now()->addHour()->toIso8601String(),
    ]), bearer($this->token))->assertStatus(422);
});

test('an image can be uploaded with a multipart submission', function () {
    Storage::fake(config('inspection.image_disk'));

    $payload = inspectionPayload([
        'overall_status' => 'DEFECT',
        'defect_count' => 1,
        'detections' => [['defect_class' => 'hole', 'confidence' => 0.88]],
    ]);

    $response = $this->post('/api/v1/inspections/results', [
        'payload' => json_encode($payload),
        'image' => UploadedFile::fake()->image('fabric.jpg', 640, 480),
    ], bearer($this->token));

    $response->assertCreated();

    $inspection = Inspection::query()->firstOrFail();

    expect($inspection->image_path)->not->toBeNull()
        ->and($inspection->image_mime)->toBe('image/jpeg')
        ->and($inspection->image_size_bytes)->toBeGreaterThan(0);

    Storage::disk(config('inspection.image_disk'))->assertExists($inspection->image_path);
});

test('an unsupported image type is rejected', function () {
    Storage::fake(config('inspection.image_disk'));

    $payload = inspectionPayload();

    $this->post('/api/v1/inspections/results', [
        'payload' => json_encode($payload),
        'image' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
    ], bearer($this->token))->assertStatus(422);

    expect(Inspection::query()->count())->toBe(0);
});

test('the legacy device_id alias is accepted', function () {
    $payload = inspectionPayload();
    unset($payload['device_code']);
    $payload['device_id'] = 'DEVICE-TEST';

    $this->postJson('/api/v1/inspections/results', $payload, bearer($this->token))
        ->assertCreated();
});

test('a token without the write ability is forbidden', function () {
    [, $readOnly] = serviceToken(['read']);

    $this->postJson('/api/v1/inspections/results', inspectionPayload(), bearer($readOnly))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

test('unauthenticated submissions are rejected', function () {
    $this->postJson('/api/v1/inspections/results', inspectionPayload())
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

test('an inactive service account cannot submit inspections', function () {
    [$service, $token] = serviceToken(['inspections:write'], ['is_active' => false]);

    $this->postJson('/api/v1/inspections/results', inspectionPayload(), bearer($token))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');
});

test('the response includes a request id header', function () {
    $response = $this->postJson('/api/v1/inspections/results', inspectionPayload(), bearer($this->token));

    $response->assertHeader('X-Request-Id');
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});
