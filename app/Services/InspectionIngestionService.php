<?php

namespace App\Services;

use App\Data\InspectionPayload;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\Inspection;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class InspectionIngestionService
{
    public function __construct(private readonly ImageStorageService $images) {}

    /**
     * Persist an inspection result and all of its detections atomically.
     */
    public function ingest(InspectionPayload $payload, ?UploadedFile $image = null): Inspection
    {
        if (! $payload->isConsistent()) {
            throw ApiException::contractMismatch(
                'overall_status and defect_count must match the submitted detections.',
                [
                    'defect_count' => $payload->defectCount,
                    'detection_count' => count($payload->detections),
                    'overall_status' => $payload->overallStatus->value,
                ],
            );
        }

        $device = Device::query()->where('device_code', $payload->deviceCode)->first();

        if ($device === null) {
            throw ApiException::unknownDevice($payload->deviceCode);
        }

        if (Inspection::query()->where('inspection_id', $payload->inspectionId)->exists()) {
            throw ApiException::duplicateInspection($payload->inspectionId);
        }

        $storedImage = null;

        try {
            $inspection = DB::transaction(function () use ($payload, $image, $device, &$storedImage): Inspection {
                if ($image !== null) {
                    $storedImage = $this->images->store($image, $payload->inspectionId);
                }

                $inspection = $device->inspections()->create([
                    'inspection_id' => $payload->inspectionId,
                    'inspected_at' => $payload->inspectedAt,
                    'received_at' => now(),
                    'overall_status' => $payload->overallStatus,
                    'defect_count' => $payload->defectCount,
                    'processing_time_ms' => $payload->processingTimeMs,
                    'model_version' => $payload->modelVersion,
                    'image_path' => $storedImage['path'] ?? null,
                    'image_mime' => $storedImage['mime'] ?? null,
                    'image_size_bytes' => $storedImage['size'] ?? null,
                    'metadata' => $payload->metadata ?: null,
                ]);

                foreach ($payload->detections as $detection) {
                    $inspection->detections()->create([
                        'detection_index' => $detection->index,
                        'defect_class' => $detection->defectClass,
                        'confidence' => $detection->confidence,
                        'bbox_x' => $detection->bboxX,
                        'bbox_y' => $detection->bboxY,
                        'bbox_width' => $detection->bboxWidth,
                        'bbox_height' => $detection->bboxHeight,
                        'metadata' => $detection->metadata ?: null,
                    ]);
                }

                $device->forceFill(['last_seen_at' => now()])->save();

                return $inspection;
            });
        } catch (Throwable $exception) {
            // Never leave an orphaned image when the database transaction fails.
            if ($storedImage !== null) {
                $this->images->delete($storedImage['path']);
            }

            if ($this->isDuplicateKey($exception)) {
                throw ApiException::duplicateInspection($payload->inspectionId);
            }

            throw $exception;
        }

        Log::info('inspection.ingested', [
            'inspection_id' => $inspection->inspection_id,
            'device_code' => $device->device_code,
            'overall_status' => $inspection->overall_status->value,
            'defect_count' => $inspection->defect_count,
            'has_image' => $inspection->image_path !== null,
        ]);

        return $inspection->load(['device', 'detections']);
    }

    private function isDuplicateKey(Throwable $exception): bool
    {
        return $exception instanceof QueryException
            && (($exception->errorInfo[1] ?? null) === 1062
                || Str::contains($exception->getMessage(), 'Duplicate entry'));
    }
}
