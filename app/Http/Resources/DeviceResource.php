<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Device */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'device_code' => $this->device_code,
            'device_name' => $this->device_name,
            'description' => $this->description,
            'location' => $this->location,
            'status' => $this->status?->value,
            'is_online' => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'inspections_count' => $this->whenCounted('inspections'),
            'latest_inspection_at' => $this->whenLoaded(
                'latestInspection',
                fn () => $this->latestInspection?->inspected_at?->toIso8601String(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
