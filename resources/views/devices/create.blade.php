<x-app-layout title="Register device">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header
            title="Register device"
            subtitle="The device code must match the identifier sent by the inference service." />

        <x-devices.form :action="route('devices.store')" method="POST" submit-label="Register device" />
    </div>
</x-app-layout>
