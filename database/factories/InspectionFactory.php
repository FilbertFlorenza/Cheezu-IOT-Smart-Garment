<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Models\Device;
use App\Models\Inspection;
use App\Models\InspectionDetection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement([InspectionStatus::Pass, InspectionStatus::Defect]);
        $defectCount = $status === InspectionStatus::Defect ? fake()->numberBetween(1, 3) : 0;

        return [
            'inspection_id' => (string) Str::uuid(),
            'device_id' => Device::factory(),
            'inspected_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'received_at' => now(),
            'overall_status' => $status,
            'defect_count' => $defectCount,
            'processing_time_ms' => fake()->numberBetween(350, 1600),
            'model_version' => fake()->randomElement(['v1.0', 'v1.1']),
            'metadata' => null,
        ];
    }

    public function passed(): static
    {
        return $this->state(fn () => [
            'overall_status' => InspectionStatus::Pass,
            'defect_count' => 0,
        ]);
    }

    public function defective(int $detections = 2): static
    {
        return $this->state(fn () => [
            'overall_status' => InspectionStatus::Defect,
            'defect_count' => $detections,
        ])->afterCreating(function (Inspection $inspection) use ($detections) {
            InspectionDetection::factory()
                ->count($detections)
                ->sequence(fn ($sequence) => ['detection_index' => $sequence->index])
                ->create(['inspection_id' => $inspection->id]);
        });
    }
}
