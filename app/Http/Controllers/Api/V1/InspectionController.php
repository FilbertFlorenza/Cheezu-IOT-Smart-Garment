<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InspectionResource;
use App\Models\Inspection;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'device' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:PASS,DEFECT'],
            'defect_class' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $inspections = Inspection::query()
            ->with(['device:id,device_code,device_name,status,last_seen_at'])
            ->withCount('detections')
            ->filter($validated)
            ->latest('inspected_at')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ApiResponse::paginated($inspections, InspectionResource::class);
    }

    public function show(Inspection $inspection): JsonResponse
    {
        $inspection->load(['device', 'detections']);

        return ApiResponse::success((new InspectionResource($inspection))->resolve(request()));
    }
}
