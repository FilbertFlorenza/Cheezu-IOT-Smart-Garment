<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased">
<div x-data="{ sidebarOpen: false }" class="min-h-full lg:flex">
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden" @click="sidebarOpen = false"></div>

    <aside x-cloak
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-slate-900 transition-transform duration-200 lg:static lg:shrink-0">
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white shadow">GQ</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-white">Garment QC</p>
                <p class="text-[11px] text-slate-400">Defect monitoring</p>
            </div>
            <button type="button" class="ml-auto text-slate-400 hover:text-white lg:hidden" @click="sidebarOpen = false" aria-label="Close navigation">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <div>
                <p class="px-3 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Monitoring</p>
                <div class="mt-2 space-y-1">
                    <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <x-slot name="icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
                        </x-slot>
                        Dashboard
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('inspections.index')" :active="request()->routeIs('inspections.*')">
                        <x-slot name="icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                        </x-slot>
                        Inspections
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('devices.index')" :active="request()->routeIs('devices.*')">
                        <x-slot name="icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/></svg>
                        </x-slot>
                        Devices
                    </x-sidebar-link>
                </div>
            </div>

            <div>
                <p class="px-3 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Forecasting</p>
                <div class="mt-2 space-y-1">
                    <x-sidebar-link :href="route('forecasts.cloud')" :active="request()->routeIs('forecasts.cloud')">
                        <x-slot name="icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z"/></svg>
                        </x-slot>
                        Cloud Forecasting
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('forecasts.network')" :active="request()->routeIs('forecasts.network')">
                        <x-slot name="icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h3.75m10.5 0H21M6.75 12a5.25 5.25 0 0 1 10.5 0M12 6.75V3m0 18v-3.75M4.5 4.5l2.25 2.25m10.5 10.5 2.25 2.25m0-15-2.25 2.25M6.75 17.25 4.5 19.5"/></svg>
                        </x-slot>
                        Network Forecasting
                    </x-sidebar-link>
                </div>
            </div>

            @if (auth()->user()?->isAdministrator())
                <div>
                    <p class="px-3 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Administration</p>
                    <div class="mt-2 space-y-1">
                        <x-sidebar-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            <x-slot name="icon">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                            </x-slot>
                            User Management
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('settings.index')" :active="request()->routeIs('settings.*')">
                            <x-slot name="icon">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.764-.383.93-.78.164-.398.142-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                            </x-slot>
                            Settings
                        </x-sidebar-link>
                    </div>
                </div>
            @endif
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                </span>
                <span>System online</span>
                <span class="ml-auto rounded bg-white/5 px-1.5 py-0.5 text-[10px] uppercase">{{ app()->environment() }}</span>
            </div>
            <p class="mt-2 text-[10px] text-slate-500">{{ display_timezone() }} · Laravel {{ app()->version() }}</p>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" @click="sidebarOpen = true" aria-label="Open navigation">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                </button>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Quality Control</p>
                    <p class="truncate text-sm font-medium text-slate-700">Fabric inspection &amp; forecasting</p>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <a href="{{ route('forecasts.cloud') }}" class="hidden items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 sm:inline-flex">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                        Forecast feed
                    </a>

                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-slate-100">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-800 text-xs font-semibold text-white">
                                {{ auth()->user()?->initials() }}
                            </span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-xs font-semibold text-slate-800">{{ auth()->user()?->name }}</span>
                                <span class="block text-[10px] text-slate-500">{{ auth()->user()?->role->label() }}</span>
                            </span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>

                        <div x-cloak x-show="open" @click.outside="open = false" x-transition
                             class="absolute right-0 mt-2 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                            <div class="border-b border-slate-100 px-3 py-2">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ auth()->user()?->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Your profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Sign out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <x-flash-message />
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200 bg-white px-4 py-4 text-xs text-slate-400 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-[1600px] flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <p>Smart Garment Defect Detection System</p>
                <p>Times shown in {{ display_timezone() }}</p>
            </div>
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
