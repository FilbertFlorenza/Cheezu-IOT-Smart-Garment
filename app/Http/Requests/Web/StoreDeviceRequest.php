<?php

namespace App\Http\Requests\Web;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Device::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('device_code')) {
            $this->merge(['device_code' => strtoupper(trim((string) $this->input('device_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'device_code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9\-_]*$/', Rule::unique('devices', 'device_code')],
            'device_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::enum(DeviceStatus::class)],
        ];
    }
}
