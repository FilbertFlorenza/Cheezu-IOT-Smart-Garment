<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\InspectionPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInspectionResultRequest;
use App\Http\Resources\InspectionResource;
use App\Services\InspectionIngestionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InspectionResultController extends Controller
{
    public function store(
        StoreInspectionResultRequest $request,
        InspectionIngestionService $service,
    ): JsonResponse {
        $payload = InspectionPayload::fromArray($request->validated());
        $inspection = $service->ingest($payload, $request->file('image'));

        return ApiResponse::success(
            (new InspectionResource($inspection))->resolve($request),
            201,
            ['duplicate' => false],
        );
    }
}
