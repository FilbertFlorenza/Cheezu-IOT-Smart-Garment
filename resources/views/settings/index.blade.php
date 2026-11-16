<x-app-layout title="Settings">
    <div class="space-y-6">
        <x-page-header
            title="System settings"
            subtitle="Runtime configuration, integration accounts, and the service tokens used by machine clients.">
            <x-slot name="actions">
                <a href="{{ route('users.index') }}"
                   class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                    Manage users
                </a>
            </x-slot>
        </x-page-header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Users" :value="number_format($system['counts']['users'])" hint="Staff accounts" tone="blue" />
            <x-stat-card label="Devices" :value="number_format($system['counts']['devices'])" hint="Registered inspection units" tone="emerald" />
            <x-stat-card label="Inspections" :value="number_format($system['counts']['inspections'])" hint="Stored inspection results" tone="slate" />
            <x-stat-card label="Forecasts" :value="number_format($system['counts']['forecasts'])" hint="Cloud and network records" tone="amber" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="panel xl:col-span-1">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Runtime</h2>
                    <p class="text-xs text-slate-500">Values resolved from the environment</p>
                </div>

                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach ([
                        ['Application', $system['app_name']],
                        ['Environment', $system['environment']],
                        ['Laravel', $system['laravel_version']],
                        ['PHP', $system['php_version']],
                        ['Storage timezone', $system['timezone']],
                        ['Display timezone', $system['display_timezone']],
                        ['Image disk', $system['image_disk']],
                        ['Online threshold', $system['online_threshold'].' minutes'],
                        ['Max image size', number_format($system['image_max_kb']).' KB'],
                    ] as $row)
                        <div class="flex items-center justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $row[0] }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $row[1] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="panel xl:col-span-2">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Defect classes</h2>
                    <p class="text-xs text-slate-500">Filter options offered by the inspection history. Unknown classes from the model are still stored.</p>
                </div>

                <div class="flex flex-wrap gap-2 p-5">
                    @foreach ($system['defect_classes'] as $class)
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                            {{ str($class)->replace('_', ' ')->title() }}
                            <span class="ml-1 font-mono text-[10px] text-slate-400">{{ $class }}</span>
                        </span>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-900">Integration endpoints</h3>
                    <dl class="mt-3 space-y-2 text-xs">
                        @foreach ([
                            ['Inspection results', 'POST /api/v1/inspections/results'],
                            ['Cloud forecast', 'POST /api/v1/forecasts/cloud'],
                            ['Network forecast', 'POST /api/v1/forecasts/network'],
                            ['Health check', 'GET /api/v1/health'],
                        ] as $endpoint)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                <dt class="font-medium text-slate-600">{{ $endpoint[0] }}</dt>
                                <dd><code class="font-mono text-[11px] text-slate-700">{{ $endpoint[1] }}</code></dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        </div>

        <section class="panel overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Service API tokens</h2>
                    <p class="text-xs text-slate-500">Tokens issued to the inference and forecasting services. Plain tokens are never stored.</p>
                </div>
                <code class="rounded bg-slate-900 px-2.5 py-1 font-mono text-[11px] text-slate-100">php artisan service:token</code>
            </div>

            @if ($serviceTokens->isEmpty())
                <div class="p-5">
                    <x-empty-state
                        title="No service tokens issued"
                        message="Issue a token with php artisan service:token so the inference or forecasting service can authenticate." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Service account</th>
                                <th>Abilities</th>
                                <th>Last used</th>
                                <th>Issued</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($serviceTokens as $token)
                                <tr>
                                    <td>
                                        <p class="font-medium text-slate-800">{{ $token->name }}</p>
                                        <p class="font-mono text-[11px] text-slate-400">{{ $token->id }}</p>
                                    </td>
                                    <td>
                                        <p class="font-medium text-slate-700">{{ $token->tokenable?->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $token->tokenable?->email }}</p>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ((array) $token->abilities as $ability)
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">{{ $ability }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-slate-500">{{ $token->last_used_at ? display_datetime($token->last_used_at) : 'Never' }}</td>
                                    <td class="text-slate-500">{{ display_date($token->created_at) }}</td>
                                    <td class="text-right">
                                        <x-confirm-form
                                            :action="route('settings.tokens.revoke', $token)"
                                            method="DELETE"
                                            title="Revoke service token?"
                                            :message="'The '.$token->tokenable?->name.' token “'.$token->name.'” will stop working immediately.'"
                                            confirm-label="Revoke"
                                            label="Revoke" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
