<?php

namespace App\Http\Resources;

use App\Models\Inspection;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Inspection */
class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'inspection_id' => $this->inspection_id,
            'device' => DeviceResource::make($this->whenLoaded('device')),
            'device_id' => $this->device_id,
            'inspected_at' => $this->inspected_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'overall_status' => $this->overall_status?->value,
            'defect_count' => $this->defect_count,
            'processing_time_ms' => $this->processing_time_ms,
            'model_version' => $this->model_version,
            'image_url' => $this->when(
                $this->image_path !== null,
                fn () => app(ImageStorageService::class)->temporaryUrl($this->image_path),
            ),
            'metadata' => $this->metadata,
            'detections' => InspectionDetectionResource::collection($this->whenLoaded('detections')),
            'detections_count' => $this->whenCounted('detections'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
