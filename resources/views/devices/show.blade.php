<x-app-layout :title="$device->device_code">
    <div class="space-y-6">
        <x-page-header
            :title="$device->device_code"
            :subtitle="$device->device_name.' · '.($device->location ?: 'No location assigned')">
            <x-slot name="meta">
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :label="$device->status->label()" :color="$device->status->color()" :dot="$device->status->dotColor()" />
                    @if ($device->isOnline())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Online
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Offline
                        </span>
                    @endif
                </div>
            </x-slot>
            <x-slot name="actions">
                <a href="{{ route('devices.index') }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">All devices</a>
                @can('update', $device)
                    <a href="{{ route('devices.edit', $device) }}" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Edit device</a>
                    @if ($device->status === \App\Enums\DeviceStatus::Inactive)
                        <x-confirm-form
                            :action="route('devices.activate', $device)"
                            method="PATCH"
                            title="Activate device?"
                            :message="'Restore '.$device->device_code.' to active monitoring.'"
                            confirm-label="Activate"
                            label="Activate device"
                            variant="neutral" />
                    @else
                        <x-confirm-form
                            :action="route('devices.deactivate', $device)"
                            method="PATCH"
                            title="Deactivate device?"
                            :message="'This device will stop being counted as active. Existing inspections remain available.'"
                            confirm-label="Deactivate"
                            label="Deactivate device" />
                    @endif
                @endcan
            </x-slot>
        </x-page-header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card label="Total inspections" :value="number_format($totalInspections)" tone="blue" />
            <x-stat-card label="Pass" :value="number_format($passCount)" tone="emerald" />
            <x-stat-card label="Defect" :value="number_format($defectCount)" tone="rose" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="panel xl:col-span-1">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Device details</h2>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Device code</dt>
                        <dd class="font-mono font-medium text-slate-800">{{ $device->device_code }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Name</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $device->device_name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Location</dt>
                        <dd class="text-right text-slate-700">{{ $device->location ?: '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Last seen</dt>
                        <dd class="text-right text-slate-700">{{ $device->last_seen_at ? display_datetime($device->last_seen_at, 'M j, Y H:i') : 'Never' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Registered</dt>
                        <dd class="text-right text-slate-700">{{ display_date($device->created_at) }}</dd>
                    </div>
                    @if ($device->description)
                        <div class="px-5 py-3">
                            <dt class="text-slate-500">Description</dt>
                            <dd class="mt-1 whitespace-pre-line text-slate-700">{{ $device->description }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="panel overflow-hidden xl:col-span-2">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Recent inspections</h2>
                    <p class="text-xs text-slate-500">Latest results submitted by this device</p>
                </div>

                @if ($inspections->isEmpty())
                    <div class="p-5">
                        <x-empty-state title="No inspections yet" message="This device has not submitted any inspection results." />
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Inspection</th>
                                    <th>Detections</th>
                                    <th>Inspected</th>
                                    <th>Model</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inspections as $inspection)
                                    <tr>
                                        <td><x-status-badge :label="$inspection->overall_status->label()" :color="$inspection->overall_status->color()" :dot="$inspection->overall_status->dotColor()" /></td>
                                        <td>
                                            <a href="{{ route('inspections.show', $inspection) }}" class="font-mono text-xs font-medium text-blue-600 hover:text-blue-700">{{ Str::limit($inspection->inspection_id, 13, '…') }}</a>
                                        </td>
                                        <td>{{ $inspection->defect_count }}</td>
                                        <td class="text-slate-500">{{ display_datetime($inspection->inspected_at) }}</td>
                                        <td class="text-slate-500">{{ $inspection->model_version }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-3">{{ $inspections->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
