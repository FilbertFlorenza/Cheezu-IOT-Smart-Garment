<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE,MAINTENANCE'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $devices = Device::query()
            ->filter($validated)
            ->withCount('inspections')
            ->orderBy('device_code')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ApiResponse::paginated($devices, DeviceResource::class);
    }
}
