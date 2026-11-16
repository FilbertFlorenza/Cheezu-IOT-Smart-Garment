<?php

use App\Enums\Role;
use App\Models\Device;
use App\Models\Inspection;
use App\Models\InspectionDetection;

test('inspection history lists results and applies filters', function () {
    $device = Device::factory()->create(['device_code' => 'DEVICE-001']);
    $other = Device::factory()->create(['device_code' => 'DEVICE-002']);

    $defect = Inspection::factory()->defective(2)->create([
        'device_id' => $device->id,
        'inspected_at' => now()->subHours(2),
        'model_version' => 'v1.1',
    ]);

    InspectionDetection::query()->where('inspection_id', $defect->id)->update(['defect_class' => 'stain']);

    $pass = Inspection::factory()->passed()->create([
        'device_id' => $other->id,
        'inspected_at' => now()->subHour(),
        'model_version' => 'v1.0',
    ]);

    $operator = staffUser(Role::Operator);

    $this->actingAs($operator)->get('/inspections')
        ->assertOk()
        ->assertSee($defect->inspection_id)
        ->assertSee('DEVICE-002');

    $this->actingAs($operator)->get('/inspections?status=DEFECT')
        ->assertOk()
        ->assertSee($defect->inspection_id)
        ->assertDontSee($pass->inspection_id);

    $this->actingAs($operator)->get('/inspections?defect_class=stain')
        ->assertOk()
        ->assertSee($defect->inspection_id);

    $this->actingAs($operator)->get('/inspections?device=DEVICE-002')
        ->assertOk()
        ->assertDontSee($defect->inspection_id);

    $this->actingAs($operator)->get('/inspections?from='.now()->addDay()->toDateString())
        ->assertOk()
        ->assertDontSee($defect->inspection_id);
});

test('inspection detail shows detections, confidence, and bounding boxes', function () {
    $inspection = Inspection::factory()->defective(2)->create(['model_version' => 'v2.3']);

    $this->actingAs(staffUser(Role::Operator))
        ->get('/inspections/'.$inspection->inspection_id)
        ->assertOk()
        ->assertSee($inspection->inspection_id)
        ->assertSee('v2.3')
        ->assertSee('Captured fabric image')
        ->assertSee('Detected defects')
        ->assertSee('Bounding box');
});

test('a pass with zero detections renders an explicit empty state', function () {
    $inspection = Inspection::factory()->passed()->create();

    $this->actingAs(staffUser(Role::Operator))
        ->get('/inspections/'.$inspection->inspection_id)
        ->assertOk()
        ->assertSee('No defects detected')
        ->assertSee('That is a valid result');
});

test('the image route returns 404 when no image was stored', function () {
    $inspection = Inspection::factory()->passed()->create();

    $this->actingAs(staffUser(Role::Operator))
        ->get('/inspections/'.$inspection->inspection_id.'/image')
        ->assertNotFound();
});

test('guests are redirected to login', function () {
    $this->get('/inspections')->assertRedirect(route('login'));
});
