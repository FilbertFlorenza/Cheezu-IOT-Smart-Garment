<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_code' => 'DEV-'.strtoupper(fake()->unique()->bothify('####??')),
            'device_name' => 'Inspection Camera '.fake()->numberBetween(1, 99),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->randomElement(['Line A', 'Line B', 'Line C', 'Cutting Room', 'Finishing']),
            'status' => DeviceStatus::Active,
            'last_seen_at' => fake()->dateTimeBetween('-2 days', 'now'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => DeviceStatus::Inactive,
            'last_seen_at' => now()->subDays(9),
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => [
            'status' => DeviceStatus::Maintenance,
            'last_seen_at' => now()->subHours(6),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn () => ['last_seen_at' => now()->subHours(5)]);
    }
}
