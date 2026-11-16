<?php

use App\Enums\Role;
use App\Models\Forecast;
use App\Models\ForecastPrediction;

test('forecast pages show an empty state before any data arrives', function () {
    $operator = staffUser(Role::Operator);

    $this->actingAs($operator)->get('/forecasts/cloud')
        ->assertOk()
        ->assertSee('No forecast received yet');

    $this->actingAs($operator)->get('/forecasts/network')
        ->assertOk()
        ->assertSee('No forecast received yet');
});

test('the cloud page renders database-driven forecast metrics', function () {
    $forecast = Forecast::factory()->cloud()->create([
        'model_version' => 'v4.2',
        'forecast_horizon_minutes' => 180,
        'generated_at' => now()->subMinutes(5),
    ]);

    ForecastPrediction::factory()->create([
        'forecast_id' => $forecast->id,
        'metric_name' => 'cpu_utilization',
        'metric_unit' => '%',
        'predicted_value' => 83.5,
        'actual_value' => 79.1,
        'predicted_at' => now()->addMinutes(30),
        'risk_level' => 'HIGH',
        'anomaly_risk' => 0.8,
    ]);

    $this->actingAs(staffUser(Role::Operator))
        ->get('/forecasts/cloud')
        ->assertOk()
        ->assertSee('CPU Utilization')
        ->assertSee('v4.2')
        ->assertSee('180 min')
        ->assertSee('Active risk warnings')
        ->assertSee('83.5');
});

test('an inactive forecast is retained in history but is not shown as current', function () {
    $old = Forecast::factory()->cloud()->superseded()->create(['generated_at' => now()->subHour()]);
    ForecastPrediction::factory()->create([
        'forecast_id' => $old->id,
        'metric_name' => 'cpu_utilization',
        'predicted_value' => 12.3,
        'predicted_at' => now()->addMinutes(10),
    ]);

    $this->actingAs(staffUser(Role::Operator))
        ->get('/forecasts/cloud')
        ->assertOk()
        ->assertSee('No forecast received yet')
        ->assertSee('Superseded');
});

test('forecast detail renders the prediction table and metadata', function () {
    $forecast = Forecast::factory()->network()->create([
        'model_version' => 'v3.1',
        'metadata' => ['link' => 'garment-plant-wan'],
    ]);

    ForecastPrediction::factory()->create([
        'forecast_id' => $forecast->id,
        'metric_name' => 'network_throughput',
        'metric_unit' => 'Mbps',
        'predicted_value' => 250.5,
        'actual_value' => null,
        'predicted_at' => now()->addMinutes(15),
    ]);

    $this->actingAs(staffUser(Role::Operator))
        ->get('/forecasts/'.$forecast->id)
        ->assertOk()
        ->assertSee('Network Throughput')
        ->assertSee('250.5')
        ->assertSee('Not reported')
        ->assertSee('garment-plant-wan');
});
