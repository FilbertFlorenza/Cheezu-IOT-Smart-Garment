<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RiskLevel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class StoreForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxPredictions = (int) config('forecast.max_predictions_per_forecast');
        $maxHorizon = (int) config('forecast.max_horizon_minutes');

        return [
            'forecast_id' => ['required', 'uuid'],
            'model_name' => ['required', 'string', 'max:100'],
            'model_version' => ['required', 'string', 'max:50'],
            'forecast_horizon_minutes' => ['required', 'integer', 'min:1', 'max:'.$maxHorizon],
            'generated_at' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
            'predictions' => ['required', 'array', 'min:1', 'max:'.$maxPredictions],
            'predictions.*.metric_name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'predictions.*.metric_unit' => ['nullable', 'string', 'max:20'],
            'predictions.*.predicted_value' => ['required', 'numeric'],
            'predictions.*.actual_value' => ['nullable', 'numeric'],
            'predictions.*.predicted_at' => ['required', 'date'],
            'predictions.*.anomaly_risk' => ['nullable', 'numeric', 'between:0,1'],
            'predictions.*.risk_level' => ['nullable', Rule::enum(RiskLevel::class)],
            'predictions.*.metadata' => ['nullable', 'array'],
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
                $seen = [];

                foreach ($data['predictions'] as $index => $prediction) {
                    $key = $prediction['metric_name'].'|'.CarbonImmutable::parse($prediction['predicted_at'])->utc()->toDateTimeString();

                    if (isset($seen[$key])) {
                        $validator->errors()->add(
                            "predictions.{$index}.predicted_at",
                            'Duplicate metric_name and predicted_at combination. Each metric may have one value per timestamp.',
                        );
                    }

                    $seen[$key] = true;
                }

                $generatedAt = CarbonImmutable::parse($data['generated_at'])->utc();

                if ($generatedAt->greaterThan(now()->addMinutes(5))) {
                    $validator->errors()->add('generated_at', 'generated_at may not be more than 5 minutes in the future.');
                }
            },
        ];
    }
}
