<?php

namespace App\Http\Resources;

use App\Models\InspectionDetection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InspectionDetection */
class InspectionDetectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'defect_class' => $this->defect_class,
            'confidence' => $this->confidence,
            'bounding_box' => $this->boundingBox(),
            'metadata' => $this->metadata,
        ];
    }
}
