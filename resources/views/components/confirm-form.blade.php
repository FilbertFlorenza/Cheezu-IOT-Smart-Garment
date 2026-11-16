@props([
    'action',
    'method' => 'POST',
    'title' => 'Confirm action',
    'message' => 'This action cannot be undone.',
    'confirmLabel' => 'Confirm',
    'label' => 'Delete',
    'variant' => 'danger',
])

@php
    $variants = [
        'danger' => 'border-rose-200 text-rose-700 hover:bg-rose-50 focus:ring-rose-500',
        'warning' => 'border-amber-200 text-amber-700 hover:bg-amber-50 focus:ring-amber-500',
        'neutral' => 'border-slate-300 text-slate-700 hover:bg-slate-50 focus:ring-slate-500',
    ];
    $buttonClass = $variants[$variant] ?? $variants['danger'];
    $confirmClass = match ($variant) {
        'warning' => 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
        'neutral' => 'bg-slate-800 hover:bg-slate-900 focus:ring-slate-500',
        default => 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500',
    };
@endphp

<div x-data="{ open: false }" class="inline-flex">
    <button type="button" @click="open = true"
            class="inline-flex items-center gap-1.5 rounded-lg border bg-white px-3 py-1.5 text-xs font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $buttonClass }}">
        {{ $label }}
    </button>

    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
        <div x-show="open" x-transition
             @keydown.escape.window="open = false"
             class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
            <p class="mt-2 text-sm text-slate-500">{{ $message }}</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="open = false"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <form method="POST" action="{{ $action }}">
                    @csrf
                    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
                        @method($method)
                    @endif
                    <button type="submit"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $confirmClass }}">
                        {{ $confirmLabel }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
