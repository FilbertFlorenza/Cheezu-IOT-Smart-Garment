<?php

use App\Enums\Role;
use App\Models\Device;
use App\Models\Forecast;
use App\Models\ForecastPrediction;
use App\Models\Inspection;

test('the dashboard renders database-driven statistics', function () {
    $device = Device::factory()->create(['device_code' => 'DEVICE-001']);
    Inspection::factory()->passed()->count(3)->create(['device_id' => $device->id]);
    Inspection::factory()->defective(2)->create(['device_id' => $device->id]);

    $this->actingAs(staffUser(Role::Administrator))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Operations dashboard')
        ->assertSee('Total inspections')
        ->assertSee('25%')
        ->assertSee('DEVICE-001');
});

test('the dashboard shows forecast summaries and warnings', function () {
    $forecast = Forecast::factory()->cloud()->create(['model_version' => 'v7.0']);

    ForecastPrediction::factory()->create([
        'forecast_id' => $forecast->id,
        'metric_name' => 'memory_utilization',
        'metric_unit' => '%',
        'predicted_value' => 91.2,
        'predicted_at' => now()->addMinutes(20),
        'risk_level' => 'CRITICAL',
        'anomaly_risk' => 0.94,
    ]);

    $this->actingAs(staffUser(Role::Operator))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Cloud Resources')
        ->assertSee('Memory Utilization')
        ->assertSee('Critical risk')
        ->assertSee('v7.0');
});

test('the dashboard renders empty states without data', function () {
    $this->actingAs(staffUser(Role::Operator))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('No inspections recorded yet')
        ->assertSee('No forecast received')
        ->assertSee('No active warnings');
});

test('operators cannot access administration screens', function (string $path) {
    $operator = staffUser(Role::Operator);

    $this->actingAs($operator)->get($path)->assertForbidden();
})->with(['/users', '/users/create', '/settings']);
