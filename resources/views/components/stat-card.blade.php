@props(['label', 'value', 'hint' => null, 'tone' => 'slate', 'icon' => null])

@php
    $tones = [
        'slate' => 'from-slate-500/10 text-slate-600 ring-slate-200',
        'blue' => 'from-blue-500/10 text-blue-600 ring-blue-200',
        'emerald' => 'from-emerald-500/10 text-emerald-600 ring-emerald-200',
        'rose' => 'from-rose-500/10 text-rose-600 ring-rose-200',
        'amber' => 'from-amber-500/10 text-amber-600 ring-amber-200',
    ];
    $toneClasses = $tones[$tone] ?? $tones['slate'];
@endphp

<div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r {{ $toneClasses }}"></div>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
            @endif
        </div>
        @if ($icon)
            <span class="rounded-lg bg-slate-50 p-2 {{ $toneClasses }} ring-1">{{ $icon }}</span>
        @endif
    </div>
</div>
