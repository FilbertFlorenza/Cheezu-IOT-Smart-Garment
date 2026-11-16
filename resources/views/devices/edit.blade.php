<x-app-layout title="Edit device">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header
            :title="'Edit '.$device->device_code"
            subtitle="Update device metadata or lifecycle status. Historical inspections are always retained.">
            <x-slot name="actions">
                <a href="{{ route('devices.show', $device) }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Back to device</a>
            </x-slot>
        </x-page-header>

        <x-devices.form :device="$device" :action="route('devices.update', $device)" method="PUT" submit-label="Save changes" />
    </div>
</x-app-layout>
