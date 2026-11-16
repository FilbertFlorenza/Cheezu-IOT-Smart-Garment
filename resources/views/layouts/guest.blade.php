<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-950 font-sans text-slate-900 antialiased">
    <div class="flex min-h-full">
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-slate-900 p-12 lg:flex">
            <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 20%, #1d4ed8 0, transparent 40%), radial-gradient(circle at 80% 70%, #0f766e 0, transparent 35%);"></div>
            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white shadow-lg">GQ</span>
                    <div>
                        <p class="text-sm font-semibold text-white">Garment QC</p>
                        <p class="text-xs text-slate-400">Smart defect detection</p>
                    </div>
                </div>

                <h1 class="mt-16 max-w-lg text-4xl font-semibold leading-tight tracking-tight text-white">
                    Real-time fabric inspection for production quality control.
                </h1>
                <p class="mt-4 max-w-md text-sm leading-6 text-slate-400">
                    Monitor ESP32-CAM inspections, review defect evidence, and anticipate cloud and network capacity from one operational dashboard.
                </p>

                <div class="mt-10 grid max-w-lg grid-cols-3 gap-4">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs text-slate-400">Inspection capture</p>
                        <p class="mt-1 text-sm font-semibold text-white">ESP32-CAM</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs text-slate-400">Inference</p>
                        <p class="mt-1 text-sm font-semibold text-white">FastAPI</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs text-slate-400">Monitoring</p>
                        <p class="mt-1 text-sm font-semibold text-white">Laravel</p>
                    </div>
                </div>
            </div>

            <p class="relative text-xs text-slate-500">Times displayed in {{ display_timezone() }} · {{ app()->environment() }}</p>
        </div>

        <div class="flex w-full items-center justify-center bg-slate-50 px-4 py-10 sm:px-8 lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white">GQ</span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Garment QC</p>
                        <p class="text-xs text-slate-500">Smart defect detection</p>
                    </div>
                </div>
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
