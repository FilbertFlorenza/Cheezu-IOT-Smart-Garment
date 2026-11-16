@php
    $isCloud = $source === \App\Enums\ForecastSource::Cloud;
    $endpoint = $isCloud ? 'POST /api/v1/forecasts/cloud' : 'POST /api/v1/forecasts/network';
@endphp

<x-app-layout :title="$source->label().' forecasting'">
    <div class="space-y-6">
        <x-page-header
            :title="$source->label().' forecasting'"
            :subtitle="$isCloud
                ? 'CPU, memory, disk and network forecasts produced by the cloud resource model.'
                : 'Throughput, packet and latency forecasts produced by the network traffic model.'">
            <x-slot name="actions">
                <a href="{{ $isCloud ? route('forecasts.network') : route('forecasts.cloud') }}"
                   class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                    {{ $isCloud ? 'Network forecasting' : 'Cloud forecasting' }}
                </a>
            </x-slot>
            <x-slot name="meta">
                <p class="text-xs text-slate-500">Ingestion endpoint: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700">{{ $endpoint }}</code></p>
            </x-slot>
        </x-page-header>

        @if (! $forecast)
            <x-empty-state
                title="No forecast received yet"
                message="Forecasting results are produced by an external service. Once it submits to the ingestion endpoint, predictions and anomaly warnings will appear here.">
                <x-slot name="action">
                    @if (auth()->user()?->isAdministrator())
                        <a href="{{ route('settings.index') }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Integration settings</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to dashboard</a>
                    @endif
                </x-slot>
            </x-empty-state>
        @else
            <section class="panel">
                <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20">{{ $forecast->model_name }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Version {{ $forecast->model_version }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Horizon {{ $forecast->forecast_horizon_minutes }} min</span>
                        <x-status-badge :label="$forecast->status->label()" :color="$forecast->status->color()" />
                        @if ($warnings->isNotEmpty())
                            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">{{ $warnings->count() }} risk {{ Str::plural('warning', $warnings->count()) }}</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-xs">
                        <div>
                            <p class="text-slate-400">Generated</p>
                            <p class="font-medium text-slate-700">{{ display_datetime($forecast->generated_at) }}</p>
                        </div>
                        <div>
                            <p class="text-slate-400">Received</p>
                            <p class="font-medium text-slate-700">{{ display_datetime($forecast->received_at) }}</p>
                        </div>
                        <div>
                            <p class="text-slate-400">Forecast ID</p>
                            <p class="font-mono text-[11px] text-slate-600">{{ Str::limit($forecast->external_id, 16, '…') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            @if ($warnings->isNotEmpty())
                <section class="panel border-rose-200">
                    <div class="border-b border-rose-100 bg-rose-50/60 px-5 py-3">
                        <h2 class="text-sm font-semibold text-rose-900">Active risk warnings</h2>
                        <p class="text-xs text-rose-700/80">High or critical anomalies reported by the model for this forecast.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($warnings as $warning)
                            <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                                <span class="h-2 w-2 rounded-full {{ $warning->risk_level->dotColor() }}"></span>
                                <p class="text-sm font-medium text-slate-800">{{ $warning->metricLabel() }}</p>
                                <x-status-badge :label="$warning->risk_level->label().' risk'" :color="$warning->risk_level->color()" />
                                <p class="text-xs text-slate-500">Predicted <span class="font-semibold text-slate-700">{{ number_format($warning->predicted_value, 2) }} {{ $warning->unit() }}</span> at {{ display_datetime($warning->predicted_at) }}</p>
                                @if ($warning->anomaly_risk !== null)
                                    <span class="ml-auto text-xs text-slate-400">Anomaly score {{ number_format($warning->anomaly_risk * 100, 0) }}%</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <x-forecast-charts :series="$series" />
        @endif

        @if ($history->isNotEmpty())
            <section class="panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Forecast history</h2>
                    <p class="text-xs text-slate-500">Previous submissions. Only the newest forecast is marked active.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Generated</th>
                                <th>Model</th>
                                <th>Horizon</th>
                                <th>Status</th>
                                <th>Predictions</th>
                                <th>Warnings</th>
                                <th class="text-right">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $item)
                                <tr>
                                    <td class="text-slate-600">{{ display_datetime($item->generated_at) }}</td>
                                    <td>
                                        <span class="font-medium text-slate-800">{{ $item->model_name }}</span>
                                        <span class="text-xs text-slate-400">· {{ $item->model_version }}</span>
                                    </td>
                                    <td class="text-slate-500">{{ $item->forecast_horizon_minutes }} min</td>
                                    <td><x-status-badge :label="$item->status->label()" :color="$item->status->color()" /></td>
                                    <td class="text-slate-600">{{ $item->predictions_count }}</td>
                                    <td>
                                        @if ($item->warning_predictions_count > 0)
                                            <span class="font-semibold text-rose-600">{{ $item->warning_predictions_count }}</span>
                                        @else
                                            <span class="text-slate-400">0</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('forecasts.show', $item) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-100 px-4 py-3">{{ $history->links() }}</div>
            </section>
        @endif
    </div>
</x-app-layout>
