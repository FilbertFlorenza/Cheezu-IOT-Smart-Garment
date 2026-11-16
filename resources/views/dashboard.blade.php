<x-app-layout title="Dashboard">
    <div class="space-y-6">
        <x-page-header
            title="Operations dashboard"
            subtitle="Live inspection quality metrics, device health, and the latest cloud and network forecasts.">
            <x-slot name="meta">
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ $summary['today'] }} inspections today
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200">
                        {{ $summary['last_24h'] }} in the last 24 hours
                    </span>
                    @if ($warningCount > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 font-medium text-rose-700 ring-1 ring-rose-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            {{ $warningCount }} active forecast {{ Str::plural('warning', $warningCount) }}
                        </span>
                    @endif
                </div>
            </x-slot>
            <x-slot name="actions">
                <a href="{{ route('inspections.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                    View inspections
                </a>
            </x-slot>
        </x-page-header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total inspections" :value="number_format($summary['total'])" hint="All recorded inspections" tone="blue" />
            <x-stat-card label="Pass" :value="number_format($summary['pass'])" hint="No defects detected" tone="emerald" />
            <x-stat-card label="Defect" :value="number_format($summary['defect'])" hint="One or more detections" tone="rose" />
            <x-stat-card label="Defect rate" :value="$summary['defect_rate'].'%'" hint="Defect inspections / total" tone="amber" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="panel xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Inspection trend</h2>
                        <p class="text-xs text-slate-500">PASS and DEFECT volume over the last 14 days</p>
                    </div>
                    <a href="{{ route('inspections.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Open history</a>
                </div>
                <div class="p-5">
                    @if ($summary['total'] > 0)
                        <div class="chart-frame"><canvas id="inspectionTrend"></canvas></div>
                    @else
                        <x-empty-state
                            title="No inspections recorded yet"
                            message="Register a device and connect the inference service to start receiving inspection results." />
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Defect distribution</h2>
                    <p class="text-xs text-slate-500">Detection classes across all inspections</p>
                </div>
                <div class="p-5">
                    @if ($defectDistribution->isNotEmpty())
                        <div class="chart-frame"><canvas id="defectMix"></canvas></div>
                    @else
                        <x-empty-state title="No defects detected" message="Defect categories will appear once the inference service reports detections." />
                    @endif
                </div>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="panel xl:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Recent inspections</h2>
                        <p class="text-xs text-slate-500">Latest results received from the inference service</p>
                    </div>
                    <a href="{{ route('inspections.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View all</a>
                </div>

                @if ($recentInspections->isEmpty())
                    <div class="p-5">
                        <x-empty-state title="No inspection data" message="Results will appear here after the first submission to the inspection API." />
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Device</th>
                                    <th>Detections</th>
                                    <th>Inspected</th>
                                    <th>Processing</th>
                                    <th class="text-right">Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentInspections as $inspection)
                                    <tr>
                                        <td>
                                            <x-status-badge
                                                :label="$inspection->overall_status->label()"
                                                :color="$inspection->overall_status->color()"
                                                :dot="$inspection->overall_status->dotColor()" />
                                        </td>
                                        <td>
                                            <div class="font-medium text-slate-800">{{ $inspection->device->device_code }}</div>
                                            <div class="text-xs text-slate-400">{{ $inspection->device->device_name }}</div>
                                        </td>
                                        <td>{{ $inspection->defect_count }}</td>
                                        <td class="text-slate-500">{{ display_datetime($inspection->inspected_at) }}</td>
                                        <td class="text-slate-500">{{ $inspection->processing_time_ms ? $inspection->processing_time_ms.' ms' : '—' }}</td>
                                        <td class="text-right">
                                            <a href="{{ route('inspections.show', $inspection) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="panel">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Device fleet</h2>
                        <p class="text-xs text-slate-500">{{ $deviceStats['online'] }} online now</p>
                    </div>
                    <a href="{{ route('devices.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Manage</a>
                </div>

                <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100 text-center">
                    <div class="px-2 py-3">
                        <p class="text-lg font-semibold text-slate-900">{{ $deviceStats['total'] }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Total</p>
                    </div>
                    <div class="px-2 py-3">
                        <p class="text-lg font-semibold text-emerald-600">{{ $deviceStats['active'] }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Active</p>
                    </div>
                    <div class="px-2 py-3">
                        <p class="text-lg font-semibold text-amber-600">{{ $deviceStats['maintenance'] }}</p>
                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Maintenance</p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($devices as $device)
                        <div class="flex items-center gap-3 px-5 py-3">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $device->isOnline() ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ $device->device_code }}</p>
                                <p class="truncate text-[11px] text-slate-400">{{ $device->location ?: 'Unassigned location' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] text-slate-500">{{ $device->last_seen_at ? display_datetime($device->last_seen_at, 'M j, H:i') : 'Never seen' }}</p>
                                <p class="text-[10px] text-slate-400">{{ $device->inspections_count }} inspections</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-xs text-slate-500">
                            No devices registered.
                            @if (auth()->user()->isAdministrator())
                                <a href="{{ route('devices.create') }}" class="font-semibold text-blue-600">Register one</a>
                            @endif
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ([
                ['source' => \App\Enums\ForecastSource::Cloud, 'forecast' => $cloudForecast, 'route' => route('forecasts.cloud')],
                ['source' => \App\Enums\ForecastSource::Network, 'forecast' => $networkForecast, 'route' => route('forecasts.network')],
            ] as $entry)
                @php
                    $forecast = $entry['forecast'];
                    $latestByMetric = $forecast
                        ? $forecast->predictions->groupBy('metric_name')->map(fn ($rows) => $rows->sortBy('predicted_at')->last())->take(3)
                        : collect();
                @endphp
                <section class="panel">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ $entry['source']->label() }}</h2>
                            <p class="text-xs text-slate-500">Latest active forecast</p>
                        </div>
                        <a href="{{ $entry['route'] }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Open</a>
                    </div>

                    @if ($forecast)
                        <div class="space-y-4 p-5">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">{{ $forecast->model_name }} · {{ $forecast->model_version }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">Horizon {{ $forecast->forecast_horizon_minutes }} min</span>
                                <x-status-badge :label="$forecast->status->label()" :color="$forecast->status->color()" />
                            </div>

                            <dl class="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <dt class="text-slate-400">Generated</dt>
                                    <dd class="mt-0.5 font-medium text-slate-700">{{ display_datetime($forecast->generated_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400">Received</dt>
                                    <dd class="mt-0.5 font-medium text-slate-700">{{ display_datetime($forecast->received_at) }}</dd>
                                </div>
                            </dl>

                            @if ($latestByMetric->isNotEmpty())
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ($latestByMetric as $metric => $prediction)
                                        <div class="rounded-lg bg-slate-50 p-2.5">
                                            <p class="truncate text-[10px] uppercase tracking-wide text-slate-400">{{ metric_label($metric, $forecast->source_type->metricConfigKey()) }}</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ number_format($prediction->predicted_value, 1) }}<span class="text-[10px] font-normal text-slate-400"> {{ $prediction->unit() }}</span></p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-5">
                            <x-empty-state
                                title="No forecast received"
                                message="Send results to the {{ $entry['source']->shortLabel() }} forecasting API to populate this panel." />
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <section class="panel">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Active forecast warnings</h2>
                    <p class="text-xs text-slate-500">High and critical risks from the latest active forecasts</p>
                </div>
                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">{{ $warningCount }}</span>
            </div>

            @if ($warnings->isEmpty())
                <div class="p-5">
                    <x-empty-state title="No active warnings" message="No high or critical anomaly risks are currently reported by either forecaster." />
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($warnings as $warning)
                        <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <span class="h-2 w-2 rounded-full {{ $warning->risk_level->dotColor() }}"></span>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $warning->forecast->source_type->shortLabel() }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-slate-800">{{ $warning->metricLabel() }} · {{ $warning->risk_level->label() }} risk</p>
                                <p class="text-[11px] text-slate-400">Predicted {{ number_format($warning->predicted_value, 1) }} {{ $warning->unit() }} at {{ display_datetime($warning->predicted_at) }}</p>
                            </div>
                            <a href="{{ route('forecasts.show', $warning->forecast) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Inspect</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    @if ($summary['total'] > 0)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    renderChart('inspectionTrend', {
                        type: 'bar',
                        data: {
                            labels: @json($trend['labels']),
                            datasets: [
                                { label: 'PASS', data: @json($trend['pass']), backgroundColor: '#10b981', borderRadius: 4, stack: 'inspections', maxBarThickness: 26 },
                                { label: 'DEFECT', data: @json($trend['defect']), backgroundColor: '#f43f5e', borderRadius: 4, stack: 'inspections', maxBarThickness: 26 },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: { stacked: true, grid: { display: false } },
                                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                            },
                            plugins: { legend: { position: 'bottom' } },
                        },
                    });

                    @if ($defectDistribution->isNotEmpty())
                        renderChart('defectMix', {
                            type: 'doughnut',
                            data: {
                                labels: @json($defectDistribution->map(fn ($row) => str($row->defect_class)->replace('_', ' ')->title())),
                                datasets: [{
                                    data: @json($defectDistribution->pluck('aggregate')),
                                    backgroundColor: ['#2563eb', '#f43f5e', '#f59e0b', '#0d9488', '#7c3aed', '#db2777', '#64748b', '#22c55e'],
                                    borderWidth: 0,
                                    hoverOffset: 4,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '66%',
                                plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 12 } } },
                            },
                        });
                    @endif
                });
            </script>
        @endpush
    @endif
</x-app-layout>
