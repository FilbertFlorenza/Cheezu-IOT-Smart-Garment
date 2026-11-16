@props(['device' => null, 'action', 'method' => 'POST', 'submitLabel' => 'Save device'])

<form method="POST" action="{{ $action }}" class="panel p-5 sm:p-6">
    @csrf
    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="device_code" class="form-label">Device code <span class="text-rose-500">*</span></label>
            <input id="device_code" name="device_code" type="text" required maxlength="50"
                   value="{{ old('device_code', $device?->device_code ?? 'DEVICE-') }}"
                   placeholder="DEVICE-001" class="form-input font-mono uppercase">
            <p class="mt-1 text-xs text-slate-400">Immutable identifier used by the inference service payload.</p>
            @error('device_code') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="device_name" class="form-label">Device name <span class="text-rose-500">*</span></label>
            <input id="device_name" name="device_name" type="text" required maxlength="100"
                   value="{{ old('device_name', $device?->device_name ?? '') }}"
                   placeholder="Line A — Inspection Camera 01" class="form-input">
            @error('device_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="location" class="form-label">Location</label>
            <input id="location" name="location" type="text" maxlength="150"
                   value="{{ old('location', $device?->location ?? '') }}"
                   placeholder="Line A" class="form-input">
            @error('location') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="status" class="form-label">Status <span class="text-rose-500">*</span></label>
            <select id="status" name="status" class="form-input">
                @foreach (\App\Enums\DeviceStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $device?->status?->value ?? 'ACTIVE') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Maintenance temporarily pauses expected device activity.</p>
            @error('status') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="1000"
                      placeholder="Mounting position, lens, firmware notes…"
                      class="form-input">{{ old('description', $device?->description ?? '') }}</textarea>
            @error('description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
        <a href="{{ $device ? route('devices.show', $device) : route('devices.index') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">{{ $submitLabel }}</button>
    </div>
</form>
