@props(['href' => '#', 'active' => false])

<a href="{{ $href }}"
   @class([
       'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
       'bg-slate-800 text-white shadow-sm' => $active,
       'text-slate-300 hover:bg-slate-800/70 hover:text-white' => ! $active,
   ])
   @if ($active) aria-current="page" @endif>
    <span @class(['h-5 w-5 shrink-0', 'text-blue-400' => $active, 'text-slate-500 group-hover:text-slate-300' => ! $active])>
        {{ $icon }}
    </span>
    <span class="truncate">{{ $slot }}</span>
    @if (isset($badge))
        <span class="ml-auto rounded-full bg-rose-500/15 px-2 py-0.5 text-[11px] font-semibold text-rose-300">{{ $badge }}</span>
    @endif
</a>
