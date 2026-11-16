<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\InspectionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreInspectionResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload');

        if ($payload !== null) {
            if (is_string($payload)) {
                try {
                    $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw ValidationException::withMessages([
                        'payload' => 'The payload field must contain valid JSON.',
                    ]);
                }
            }

            if (! is_array($payload)) {
                throw ValidationException::withMessages([
                    'payload' => 'The payload field must contain a JSON object.',
                ]);
            }

            $this->merge($payload);
        }

        // Backwards-compatible alias used by the initial draft contract.
        if (! $this->filled('device_code') && $this->filled('device_id')) {
            $this->merge(['device_code' => $this->input('device_id')]);
        }

        if ($this->filled('device_code')) {
            $this->merge(['device_code' => trim((string) $this->input('device_code'))]);
        }
    }

    public function rules(): array
    {
        $maxKb = (int) config('inspection.image_max_kb');
        $maxDimension = (int) config('inspection.image_max_dimension');

        return [
            'inspection_id' => ['required', 'uuid'],
            'device_code' => ['required', 'string', 'max:50'],
            'inspected_at' => ['required', 'date'],
            'overall_status' => ['required', Rule::enum(InspectionStatus::class)],
            'defect_count' => ['required', 'integer', 'min:0', 'max:65535'],
            'processing_time_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'model_version' => ['required', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'detections' => ['present', 'array', 'max:100'],
            'detections.*.defect_class' => ['required', 'string', 'max:50'],
            'detections.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'detections.*.bounding_box' => ['nullable', 'array'],
            'detections.*.bounding_box.x' => ['required_with:detections.*.bounding_box', 'integer', 'min:0'],
            'detections.*.bounding_box.y' => ['required_with:detections.*.bounding_box', 'integer', 'min:0'],
            'detections.*.bounding_box.width' => ['required_with:detections.*.bounding_box', 'integer', 'min:1'],
            'detections.*.bounding_box.height' => ['required_with:detections.*.bounding_box', 'integer', 'min:1'],
            'detections.*.metadata' => ['nullable', 'array'],
            'image' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.$maxKb,
                'dimensions:max_width='.$maxDimension.',max_height='.$maxDimension,
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $data = $validator->getData();
                $detectionCount = count($data['detections'] ?? []);
                $defectCount = (int) $data['defect_count'];

                if ($defectCount !== $detectionCount) {
                    $validator->errors()->add(
                        'defect_count',
                        "defect_count ({$defectCount}) must equal the number of submitted detections ({$detectionCount}).",
                    );
                }

                $expected = $detectionCount > 0 ? InspectionStatus::Defect->value : InspectionStatus::Pass->value;

                if (($data['overall_status'] ?? null) !== $expected) {
                    $validator->errors()->add(
                        'overall_status',
                        "overall_status must be {$expected} for a submission with {$detectionCount} detections.",
                    );
                }

                $inspectedAt = CarbonImmutable::parse($data['inspected_at'])->utc();
                $tolerance = (int) config('inspection.future_tolerance_minutes');

                if ($inspectedAt->greaterThan(now()->addMinutes($tolerance))) {
                    $validator->errors()->add('inspected_at', 'inspected_at may not be more than '.$tolerance.' minutes in the future.');
                }

                $backfill = (int) config('inspection.backfill_days');

                if ($inspectedAt->lessThan(now()->subDays($backfill))) {
                    $validator->errors()->add('inspected_at', 'inspected_at may not be older than '.$backfill.' days.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'detections.present' => 'The detections field is required. Submit an empty array for a PASS with no defects.',
        ];
    }
}
