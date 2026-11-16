<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-4 font-sans antialiased">
    <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">GQ</div>
        <p class="mt-6 text-xs font-semibold uppercase tracking-widest text-slate-400">@yield('code')</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">@yield('heading')</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">@yield('message')</p>
        <div class="mt-7 flex items-center justify-center gap-3">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
               class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                {{ auth()->check() ? 'Back to dashboard' : 'Go to sign in' }}
            </a>
        </div>
    </div>
</body>
</html>
