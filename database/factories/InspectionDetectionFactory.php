<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionDetection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionDetection>
 */
class InspectionDetectionFactory extends Factory
{
    public function definition(): array
    {
        $x = fake()->numberBetween(0, 900);
        $y = fake()->numberBetween(0, 600);

        return [
            'inspection_id' => Inspection::factory(),
            'detection_index' => 0,
            'defect_class' => fake()->randomElement(config('inspection.defect_classes')),
            'confidence' => fake()->randomFloat(4, 0.55, 0.99),
            'bbox_x' => $x,
            'bbox_y' => $y,
            'bbox_width' => fake()->numberBetween(20, 160),
            'bbox_height' => fake()->numberBetween(20, 160),
            'metadata' => null,
        ];
    }
}
