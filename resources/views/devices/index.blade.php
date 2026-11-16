<x-app-layout title="Devices">
    <div class="space-y-6">
        <x-page-header
            title="Devices"
            subtitle="Registered ESP32-CAM inspection units, their liveness, and inspection output.">
            <x-slot name="actions">
                @can('create', \App\Models\Device::class)
                    <a href="{{ route('devices.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Register device
                    </a>
                @endcan
            </x-slot>
        </x-page-header>

        <form method="GET" action="{{ route('devices.index') }}" class="panel p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Device code, name, or location" class="form-input">
                </div>
                <div class="w-full sm:w-48">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="">All statuses</option>
                        @foreach (\App\Enums\DeviceStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('devices.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Reset</a>
                </div>
            </div>
        </form>

        <section class="panel overflow-hidden">
            @if ($devices->isEmpty())
                <div class="p-6">
                    <x-empty-state
                        title="No devices found"
                        message="Register the ESP32-CAM units that will submit inspection results.">
                        @can('create', \App\Models\Device::class)
                            <x-slot name="action">
                                <a href="{{ route('devices.create') }}" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Register a device</a>
                            </x-slot>
                        @endcan
                    </x-empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Last seen</th>
                                <th>Inspections</th>
                                <th>Last inspection</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($devices as $device)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <span class="h-2 w-2 rounded-full {{ $device->isOnline() ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                            <div>
                                                <a href="{{ route('devices.show', $device) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $device->device_code }}</a>
                                                <div class="text-[11px] text-slate-400">{{ $device->device_name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-slate-500">{{ $device->location ?: '—' }}</td>
                                    <td>
                                        <x-status-badge :label="$device->status->label()" :color="$device->status->color()" :dot="$device->status->dotColor()" />
                                    </td>
                                    <td class="text-slate-500">
                                        {{ $device->last_seen_at ? display_datetime($device->last_seen_at) : 'Never' }}
                                        @if ($device->isOnline())
                                            <span class="ml-1 text-[10px] font-semibold uppercase text-emerald-600">online</span>
                                        @endif
                                    </td>
                                    <td class="text-slate-600">{{ $device->inspections_count }}</td>
                                    <td class="text-slate-500">{{ $device->latestInspection ? display_datetime($device->latestInspection->inspected_at) : '—' }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('devices.show', $device) }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">View</a>
                                            @can('update', $device)
                                                <a href="{{ route('devices.edit', $device) }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Edit</a>
                                                @if ($device->status === \App\Enums\DeviceStatus::Inactive)
                                                    <x-confirm-form
                                                        :action="route('devices.activate', $device)"
                                                        method="PATCH"
                                                        title="Activate device?"
                                                        :message="'Restore '.$device->device_code.' to active monitoring.'"
                                                        confirm-label="Activate"
                                                        label="Activate"
                                                        variant="neutral" />
                                                @else
                                                    <x-confirm-form
                                                        :action="route('devices.deactivate', $device)"
                                                        method="PATCH"
                                                        title="Deactivate device?"
                                                        :message="'The device will stop being counted as active. Historical inspections are retained.'"
                                                        confirm-label="Deactivate"
                                                        label="Deactivate" />
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-100 px-4 py-3">{{ $devices->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
