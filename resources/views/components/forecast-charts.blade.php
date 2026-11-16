@props(['series'])

@if (count($series) > 0)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($series as $metricSeries)
            <div class="panel p-5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $metricSeries['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">
                            {{ number_format($metricSeries['latest_predicted'], 1) }}
                            <span class="text-xs font-normal text-slate-400">{{ $metricSeries['unit'] }}</span>
                        </p>
                    </div>
                    @if ($metricSeries['latest_risk'])
                        <x-status-badge
                            :label="\App\Enums\RiskLevel::from($metricSeries['latest_risk'])->label()"
                            :color="\App\Enums\RiskLevel::from($metricSeries['latest_risk'])->color()" />
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-slate-400">
                    Latest predicted value
                    @if ($metricSeries['latest_actual'] !== null)
                        · actual {{ number_format($metricSeries['latest_actual'], 1) }} {{ $metricSeries['unit'] }}
                    @endif
                </p>
                <div class="mt-4 h-40"><canvas id="forecast-chart-{{ $loop->index }}"></canvas></div>
                @if (! $metricSeries['has_actual'])
                    <p class="mt-2 text-[10px] text-amber-600">No actual values reported for this metric yet.</p>
                @endif
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const series = @json($series);

                series.forEach((metric, index) => {
                    const datasets = [{
                        label: 'Predicted (model)',
                        data: metric.predicted,
                        borderColor: metric.color,
                        backgroundColor: metric.color + '22',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                    }];

                    if (metric.has_actual) {
                        datasets.push({
                            label: 'Actual (reported)',
                            data: metric.actual,
                            borderColor: '#0f172a',
                            backgroundColor: 'transparent',
                            borderDash: [5, 4],
                            fill: false,
                            tension: 0.25,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            borderWidth: 1.6,
                            spanGaps: true,
                        });
                    }

                    renderChart(`forecast-chart-${index}`, {
                        type: 'line',
                        data: { labels: metric.labels, datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: { legend: { display: true, position: 'bottom' } },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 6 } },
                                y: { beginAtZero: true },
                            },
                        },
                    });
                });
            });
        </script>
    @endpush
@else
    <x-empty-state title="No predictions stored" message="This forecast record does not contain any predictions." />
@endif
