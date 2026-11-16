<x-app-layout :title="$source->shortLabel().' forecast detail'">
    <div class="space-y-6">
        <x-page-header
            :title="$source->label().' forecast'"
            :subtitle="'Model '.$forecast->model_name.' · '.$forecast->model_version.' · generated '.display_datetime($forecast->generated_at)">
            <x-slot name="actions">
                <a href="{{ route($source->route()) }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Back to forecasting</a>
            </x-slot>
            <x-slot name="meta">
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :label="$forecast->status->label()" :color="$forecast->status->color()" />
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Horizon {{ $forecast->forecast_horizon_minutes }} min</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $forecast->predictions->count() }} predictions</span>
                    @if ($warnings->isNotEmpty())
                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">{{ $warnings->count() }} warnings</span>
                    @endif
                </div>
            </x-slot>
        </x-page-header>

        @if ($warnings->isNotEmpty())
            <section class="panel border-rose-200 p-5">
                <h2 class="text-sm font-semibold text-rose-900">Risk warnings</h2>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($warnings as $warning)
                        <div class="rounded-lg border border-rose-100 bg-rose-50/50 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-slate-800">{{ $warning->metricLabel() }}</p>
                                <x-status-badge :label="$warning->risk_level->label()" :color="$warning->risk_level->color()" />
                            </div>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ number_format($warning->predicted_value, 2) }} <span class="text-xs font-normal text-slate-500">{{ $warning->unit() }}</span></p>
                            <p class="text-[11px] text-slate-500">{{ display_datetime($warning->predicted_at) }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <x-forecast-charts :series="$series" />

        @foreach ($forecast->predictions->groupBy('metric_name') as $metric => $predictions)
            <section class="panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ metric_label($metric, $source->metricConfigKey()) }}</h2>
                        <p class="text-xs text-slate-500">Metric <code class="font-mono">{{ $metric }}</code> · {{ $predictions->first()->unit() ?: 'unit not supplied' }}</p>
                    </div>
                    <span class="text-xs text-slate-400">{{ $predictions->count() }} timestamps</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Predicted at</th>
                                <th>Predicted value</th>
                                <th>Actual value</th>
                                <th>Anomaly risk</th>
                                <th>Risk level</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($predictions->sortBy('predicted_at') as $prediction)
                                <tr>
                                    <td class="text-slate-500">{{ display_datetime($prediction->predicted_at, 'M j, Y H:i') }}</td>
                                    <td class="font-medium text-slate-800">{{ number_format($prediction->predicted_value, 2) }}</td>
                                    <td class="{{ $prediction->actual_value === null ? 'text-slate-400' : 'font-medium text-slate-800' }}">
                                        {{ $prediction->actual_value === null ? 'Not reported' : number_format($prediction->actual_value, 2) }}
                                    </td>
                                    <td class="text-slate-500">{{ $prediction->anomaly_risk === null ? '—' : number_format($prediction->anomaly_risk * 100, 0).'%' }}</td>
                                    <td>
                                        @if ($prediction->risk_level)
                                            <x-status-badge :label="$prediction->risk_level->label()" :color="$prediction->risk_level->color()" />
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <section class="panel p-5">
            <h2 class="text-sm font-semibold text-slate-900">Forecast metadata</h2>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-slate-400">Forecast ID</dt>
                    <dd class="mt-0.5 break-all font-mono text-[11px] text-slate-700">{{ $forecast->external_id }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Received</dt>
                    <dd class="mt-0.5 font-medium text-slate-700">{{ display_datetime($forecast->received_at, 'M j, Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Source</dt>
                    <dd class="mt-0.5 font-medium text-slate-700">{{ $source->label() }}</dd>
                </div>
            </dl>

            @if ($forecast->metadata)
                <pre class="mt-4 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-6 text-slate-200">{{ json_encode($forecast->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </section>
    </div>
</x-app-layout>
