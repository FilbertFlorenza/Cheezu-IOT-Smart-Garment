<x-app-layout title="Inspections">
    <div class="space-y-6">
        <x-page-header
            title="Inspection history"
            subtitle="Every result received from the ESP32-CAM inference pipeline, with defect evidence and model metadata.">
            <x-slot name="actions">
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Dashboard</a>
            </x-slot>
        </x-page-header>

        <form method="GET" action="{{ route('inspections.index') }}" class="panel p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="UUID, device, model version" class="form-input">
                </div>

                <div>
                    <label for="device" class="form-label">Device</label>
                    <select id="device" name="device" class="form-input">
                        <option value="">All devices</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->device_code }}" @selected(($filters['device'] ?? '') === $device->device_code)>
                                {{ $device->device_code }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="">All statuses</option>
                        <option value="PASS" @selected(($filters['status'] ?? '') === 'PASS')>Pass</option>
                        <option value="DEFECT" @selected(($filters['status'] ?? '') === 'DEFECT')>Defect</option>
                    </select>
                </div>

                <div>
                    <label for="defect_class" class="form-label">Defect class</label>
                    <select id="defect_class" name="defect_class" class="form-input">
                        <option value="">All classes</option>
                        @foreach ($defectClasses as $class)
                            <option value="{{ $class }}" @selected(($filters['defect_class'] ?? '') === $class)>
                                {{ str($class)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="from" class="form-label">From</label>
                        <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-input">
                    </div>
                    <div>
                        <label for="to" class="form-label">To</label>
                        <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="form-input">
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Apply filters</button>
                <a href="{{ route('inspections.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Reset</a>
                <p class="ml-auto text-xs text-slate-500">{{ number_format($inspections->total()) }} matching inspections</p>
            </div>
        </form>

        <section class="panel overflow-hidden">
            @if ($inspections->isEmpty())
                <div class="p-6">
                    <x-empty-state
                        title="No inspections match these filters"
                        message="Adjust the search criteria, or wait for the inference service to submit new results.">
                        <x-slot name="action">
                            <a href="{{ route('inspections.index') }}" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Clear filters</a>
                        </x-slot>
                    </x-empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Inspection</th>
                                <th>Device</th>
                                <th>Detections</th>
                                <th>Inspected at</th>
                                <th>Processing</th>
                                <th>Model</th>
                                <th class="text-right">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inspections as $inspection)
                                <tr>
                                    <td>
                                        <x-status-badge
                                            :label="$inspection->overall_status->label()"
                                            :color="$inspection->overall_status->color()"
                                            :dot="$inspection->overall_status->dotColor()" />
                                    </td>
                                    <td>
                                        <a href="{{ route('inspections.show', $inspection) }}" class="font-mono text-xs font-medium text-blue-600 hover:text-blue-700">
                                            {{ Str::limit($inspection->inspection_id, 13, '…') }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-800">{{ $inspection->device->device_code }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $inspection->device->location ?: $inspection->device->device_name }}</div>
                                    </td>
                                    <td>
                                        <span class="font-medium text-slate-700">{{ $inspection->defect_count }}</span>
                                        <span class="text-xs text-slate-400">/ {{ $inspection->detections_count }}</span>
                                    </td>
                                    <td class="text-slate-500">{{ display_datetime($inspection->inspected_at) }}</td>
                                    <td class="text-slate-500">{{ $inspection->processing_time_ms ? $inspection->processing_time_ms.' ms' : '—' }}</td>
                                    <td class="text-slate-500">{{ $inspection->model_version }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('inspections.show', $inspection) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-100 px-4 py-3">
                    {{ $inspections->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
