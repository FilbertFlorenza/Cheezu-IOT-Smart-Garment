<?php

use App\Models\Device;
use App\Models\Forecast;
use App\Models\Inspection;
use App\Models\InspectionDetection;

beforeEach(function () {
    [, $this->token] = serviceToken(['read']);
});

test('the health endpoint reports database connectivity without authentication', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.checks.database', 'ok');
});

test('devices are listed with pagination metadata', function () {
    Device::factory()->count(3)->create();

    $this->getJson('/api/v1/devices', bearer($this->token))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonStructure(['data' => [['device_code', 'device_name', 'status', 'is_online', 'inspections_count']]]);
});

test('inspection listings support filters and expose detections', function () {
    $device = Device::factory()->create(['device_code' => 'DEVICE-A']);
    $other = Device::factory()->create(['device_code' => 'DEVICE-B']);

    $defective = Inspection::factory()->defective(2)->create([
        'device_id' => $device->id,
        'inspected_at' => now()->subMinutes(10),
    ]);

    InspectionDetection::query()->where('inspection_id', $defective->id)->update(['defect_class' => 'hole']);

    Inspection::factory()->passed()->create([
        'device_id' => $other->id,
        'inspected_at' => now()->subMinutes(5),
    ]);

    $this->getJson('/api/v1/inspections?status=DEFECT', bearer($this->token))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.inspection_id', $defective->inspection_id)
        ->assertJsonPath('data.0.detections_count', 2);

    $this->getJson('/api/v1/inspections?device=DEVICE-B', bearer($this->token))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.overall_status', 'PASS');

    $this->getJson('/api/v1/inspections?defect_class=hole', bearer($this->token))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('inspection detail is resolved by inspection uuid', function () {
    $inspection = Inspection::factory()->defective(1)->create();

    $this->getJson('/api/v1/inspections/'.$inspection->inspection_id, bearer($this->token))
        ->assertOk()
        ->assertJsonPath('data.inspection_id', $inspection->inspection_id)
        ->assertJsonCount(1, 'data.detections')
        ->assertJsonStructure(['data' => ['detections' => [['defect_class', 'confidence', 'bounding_box']]]]);
});

test('an unknown inspection returns the JSON error envelope', function () {
    $this->getJson('/api/v1/inspections/00000000-0000-4000-8000-000000000000', bearer($this->token))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

test('forecast listings return the latest active record', function () {
    Forecast::factory()->cloud()->superseded()->create(['generated_at' => now()->subHour()]);
    $active = Forecast::factory()->cloud()->create(['generated_at' => now()]);

    $this->getJson('/api/v1/forecasts/cloud?latest=1', bearer($this->token))
        ->assertOk()
        ->assertJsonPath('data.forecast_id', $active->external_id)
        ->assertJsonPath('data.status', 'ACTIVE');
});

test('an empty forecast feed returns null data', function () {
    $this->getJson('/api/v1/forecasts/network?latest=1', bearer($this->token))
        ->assertOk()
        ->assertJsonPath('data', null);
});

test('read endpoints require a token', function () {
    $this->getJson('/api/v1/devices')->assertStatus(401);
});
