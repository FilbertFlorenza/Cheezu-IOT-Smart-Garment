<?php

use App\Enums\ForecastStatus;
use App\Enums\RiskLevel;
use App\Models\Forecast;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->cloudService, $this->cloudToken] = serviceToken(['forecasts:cloud:write', 'read']);
    [, $this->networkToken] = serviceToken(['forecasts:network:write']);
});

function forecastPayload(array $overrides = []): array
{
    return array_merge([
        'forecast_id' => (string) Str::uuid(),
        'model_name' => 'cloud-lstm',
        'model_version' => 'v1.0',
        'forecast_horizon_minutes' => 120,
        'generated_at' => now()->toIso8601String(),
        'metadata' => ['region' => 'ap-southeast-1'],
        'predictions' => [
            [
                'metric_name' => 'cpu_utilization',
                'metric_unit' => '%',
                'predicted_value' => 71.5,
                'actual_value' => 68.0,
                'predicted_at' => now()->addMinutes(30)->toIso8601String(),
                'anomaly_risk' => 0.82,
                'risk_level' => 'HIGH',
            ],
            [
                'metric_name' => 'cpu_utilization',
                'metric_unit' => '%',
                'predicted_value' => 75.2,
                'predicted_at' => now()->addMinutes(60)->toIso8601String(),
            ],
            [
                'metric_name' => 'memory_utilization',
                'metric_unit' => '%',
                'predicted_value' => 88.4,
                'predicted_at' => now()->addMinutes(30)->toIso8601String(),
            ],
        ],
    ], $overrides);
}

test('cloud forecasts are stored with all predictions', function () {
    $payload = forecastPayload();

    $this->postJson('/api/v1/forecasts/cloud', $payload, bearer($this->cloudToken))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.source', 'CLOUD')
        ->assertJsonPath('data.status', 'ACTIVE')
        ->assertJsonPath('data.model_version', 'v1.0')
        ->assertJsonCount(3, 'data.predictions')
        ->assertJsonPath('data.predictions.0.metric_label', 'CPU Utilization');

    $forecast = Forecast::query()->with('predictions')->firstOrFail();

    expect($forecast->predictions)->toHaveCount(3)
        ->and($forecast->source_type->value)->toBe('CLOUD')
        ->and($forecast->predictions->first(fn ($prediction) => $prediction->risk_level === RiskLevel::High)->anomaly_risk)->toBe(0.82)
        ->and($forecast->predictions->firstWhere('metric_name', 'memory_utilization')->actual_value)->toBeNull();
});

test('a second submission supersedes the previously active forecast', function () {
    $first = forecastPayload(['model_version' => 'v1.0']);

    $this->postJson('/api/v1/forecasts/cloud', $first, bearer($this->cloudToken))->assertCreated();
    $this->postJson('/api/v1/forecasts/cloud', forecastPayload(['model_version' => 'v2.0']), bearer($this->cloudToken))->assertCreated();

    $versions = Forecast::query()->orderBy('id')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->status)->toBe(ForecastStatus::Superseded)
        ->and($versions[1]->status)->toBe(ForecastStatus::Active);

    $this->getJson('/api/v1/forecasts/cloud?latest=1', bearer($this->cloudToken))
        ->assertOk()
        ->assertJsonPath('data.model_version', 'v2.0');
});

test('a duplicate forecast id is rejected for the same source', function () {
    $payload = forecastPayload();

    $this->postJson('/api/v1/forecasts/cloud', $payload, bearer($this->cloudToken))->assertCreated();

    $this->postJson('/api/v1/forecasts/cloud', $payload, bearer($this->cloudToken))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'DUPLICATE_FORECAST');

    expect(Forecast::query()->count())->toBe(1);
});

test('network forecasts can use the same forecast id as a cloud forecast', function () {
    $payload = forecastPayload([
        'model_name' => 'network-prophet',
        'predictions' => [
            ['metric_name' => 'network_throughput', 'metric_unit' => 'Mbps', 'predicted_value' => 312.7, 'predicted_at' => now()->addMinutes(20)->toIso8601String(), 'risk_level' => 'HIGH'],
        ],
    ]);

    $this->postJson('/api/v1/forecasts/cloud', $payload, bearer($this->cloudToken))->assertCreated();
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/forecasts/network', $payload, bearer($this->networkToken))
        ->assertCreated()
        ->assertJsonPath('data.source', 'NETWORK');

    expect(Forecast::query()->count())->toBe(2);
});

test('cloud tokens cannot submit network forecasts', function () {
    $payload = forecastPayload();

    $this->postJson('/api/v1/forecasts/network', $payload, bearer($this->cloudToken))
        ->assertStatus(403);
});

test('duplicate metric timestamps inside one payload are rejected', function () {
    $payload = forecastPayload();

    $payload['predictions'][] = $payload['predictions'][0];

    $this->postJson('/api/v1/forecasts/cloud', $payload, bearer($this->cloudToken))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(Forecast::query()->count())->toBe(0);
});

test('predictions are validated', function () {
    $payload = forecastPayload();
    $payload['predictions'][0]['predicted_value'] = 'not-a-number';
    $payload['predictions'][1]['metric_name'] = 'Invalid Metric!';
    $payload['predictions'][2]['risk_level'] = 'SEVERE';

    $this->postJson('/api/v1/forecasts/cloud', $payload, bearer($this->cloudToken))
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => [
            'predictions.0.predicted_value',
            'predictions.1.metric_name',
            'predictions.2.risk_level',
        ]]]);
});

test('a forecast with no predictions is rejected', function () {
    $this->postJson('/api/v1/forecasts/cloud', forecastPayload(['predictions' => []]), bearer($this->cloudToken))
        ->assertStatus(422);
});

test('unauthenticated forecast submissions are rejected', function () {
    $this->postJson('/api/v1/forecasts/cloud', forecastPayload())
        ->assertStatus(401);
});
