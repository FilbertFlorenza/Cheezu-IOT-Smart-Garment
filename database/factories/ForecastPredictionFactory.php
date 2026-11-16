<?php

namespace Database\Factories;

use App\Enums\RiskLevel;
use App\Models\Forecast;
use App\Models\ForecastPrediction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastPrediction>
 */
class ForecastPredictionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'forecast_id' => Forecast::factory(),
            'metric_name' => 'cpu_utilization',
            'metric_unit' => '%',
            'predicted_value' => fake()->randomFloat(2, 10, 95),
            'actual_value' => fake()->optional(0.6)->randomFloat(2, 10, 95),
            'predicted_at' => now()->addMinutes(15),
            'anomaly_risk' => fake()->optional()->randomFloat(4, 0, 1),
            'risk_level' => fake()->randomElement(RiskLevel::cases()),
            'metadata' => null,
        ];
    }
}
