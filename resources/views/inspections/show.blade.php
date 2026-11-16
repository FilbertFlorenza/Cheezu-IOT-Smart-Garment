<x-app-layout :title="'Inspection '.Str::limit($inspection->inspection_id, 8, '')">
    <div class="space-y-6">
        <x-page-header
            :title="'Inspection '.Str::limit($inspection->inspection_id, 18, '…')"
            :subtitle="$inspection->device->device_code.' · '.$inspection->device->device_name.' · '.display_datetime($inspection->inspected_at)">
            <x-slot name="actions">
                <a href="{{ route('inspections.index') }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Back to history</a>
                <a href="{{ route('devices.show', $inspection->device) }}" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Open device</a>
            </x-slot>
            <x-slot name="meta">
                <x-status-badge
                    :label="$inspection->overall_status->label()"
                    :color="$inspection->overall_status->color()"
                    :dot="$inspection->overall_status->dotColor()" />
            </x-slot>
        </x-page-header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="panel overflow-hidden xl:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Captured fabric image</h2>
                        <p class="text-xs text-slate-500">Source frame submitted with this inspection</p>
                    </div>
                    @if ($inspection->image_size_bytes)
                        <span class="text-xs text-slate-400">{{ number_format($inspection->image_size_bytes / 1024, 0) }} KB</span>
                    @endif
                </div>

                <div class="bg-slate-100/70 p-4">
                    @if ($imageUrl)
                        <img src="{{ $imageUrl }}" alt="Inspection {{ $inspection->inspection_id }}"
                             class="mx-auto max-h-[520px] w-auto rounded-lg border border-slate-200 bg-white object-contain shadow-sm">
                    @else
                        <div class="flex h-72 flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white text-center">
                            <svg class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/>
                            </svg>
                            <p class="mt-3 text-sm font-medium text-slate-600">No image uploaded</p>
                            <p class="mt-1 text-xs text-slate-400">The inference service did not attach an image to this inspection.</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Inspection record</h2>
                    <p class="text-xs text-slate-500">Contract metadata and device context</p>
                </div>

                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex items-start justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Inspection ID</dt>
                        <dd class="max-w-[60%] break-all text-right font-mono text-[11px] text-slate-700">{{ $inspection->inspection_id }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Device</dt>
                        <dd class="text-right font-medium text-slate-800">
                            <a href="{{ route('devices.show', $inspection->device) }}" class="text-blue-600 hover:text-blue-700">{{ $inspection->device->device_code }}</a>
                            <div class="text-[11px] text-slate-400">{{ $inspection->device->location ?: 'No location' }}</div>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Inspected at</dt>
                        <dd class="text-right font-medium text-slate-800">{{ display_datetime($inspection->inspected_at, 'M j, Y H:i:s') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Received at</dt>
                        <dd class="text-right text-slate-700">{{ display_datetime($inspection->received_at, 'M j, Y H:i:s') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Defect count</dt>
                        <dd class="text-right font-semibold {{ $inspection->defect_count > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $inspection->defect_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Processing time</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $inspection->processing_time_ms ? $inspection->processing_time_ms.' ms' : 'Not reported' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Model version</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $inspection->model_version }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Image mime</dt>
                        <dd class="text-right text-slate-700">{{ $inspection->image_mime ?: '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Detected defects</h2>
                    <p class="text-xs text-slate-500">{{ $inspection->detections->count() }} detection {{ Str::plural('record', $inspection->detections->count()) }} stored for this inspection</p>
                </div>
                <span class="text-xs text-slate-400">Coordinates in image pixels</span>
            </div>

            @if ($inspection->detections->isEmpty())
                <div class="p-5">
                    <x-empty-state title="No defects detected" message="This inspection passed with zero stored detections. That is a valid result." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Defect class</th>
                                <th>Confidence</th>
                                <th>Bounding box</th>
                                <th>Metadata</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inspection->detections as $detection)
                                <tr>
                                    <td class="text-slate-400">{{ $loop->iteration }}</td>
                                    <td>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                            {{ str($detection->defect_class)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full rounded-full bg-blue-600" style="width: {{ round($detection->confidence * 100) }}%"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-slate-700">{{ number_format($detection->confidence * 100, 1) }}%</span>
                                        </div>
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">
                                        @if ($detection->boundingBox())
                                            x {{ $detection->boundingBox()['x'] }}, y {{ $detection->boundingBox()['y'] }}
                                            · {{ $detection->boundingBox()['width'] }}×{{ $detection->boundingBox()['height'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="max-w-xs truncate text-xs text-slate-400">
                                        {{ $detection->metadata ? json_encode($detection->metadata) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($inspection->metadata)
            <section class="panel p-5">
                <h2 class="text-sm font-semibold text-slate-900">Additional metadata</h2>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-6 text-slate-200">{{ json_encode($inspection->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </section>
        @endif
    </div>
</x-app-layout>
