@props(['label', 'color' => 'bg-slate-100 text-slate-600 ring-slate-500/20', 'dot' => null])

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $color }}">
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
    @endif
    {{ $label }}
</span>
