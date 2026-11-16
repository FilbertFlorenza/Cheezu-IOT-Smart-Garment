<?php

namespace Database\Factories;

use App\Enums\ForecastSource;
use App\Enums\ForecastStatus;
use App\Models\Forecast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Forecast>
 */
class ForecastFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => (string) Str::uuid(),
            'source_type' => ForecastSource::Cloud,
            'model_name' => fake()->randomElement(['cloud-lstm', 'cloud-prophet']),
            'model_version' => fake()->randomElement(['v1.0', 'v1.2']),
            'forecast_horizon_minutes' => fake()->randomElement([60, 120, 240]),
            'generated_at' => now(),
            'received_at' => now(),
            'status' => ForecastStatus::Active,
            'metadata' => null,
        ];
    }

    public function cloud(): static
    {
        return $this->state(fn () => [
            'source_type' => ForecastSource::Cloud,
            'model_name' => 'cloud-lstm',
        ]);
    }

    public function network(): static
    {
        return $this->state(fn () => [
            'source_type' => ForecastSource::Network,
            'model_name' => 'network-prophet',
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn () => ['status' => ForecastStatus::Superseded]);
    }
}
