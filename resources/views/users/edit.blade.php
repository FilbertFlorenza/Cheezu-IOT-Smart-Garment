<x-app-layout title="Edit user">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header :title="'Edit '.$user->name" :subtitle="$user->email">
            <x-slot name="actions">
                <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Back to users</a>
            </x-slot>
        </x-page-header>
        <x-users.form :user="$user" :action="route('users.update', $user)" method="PUT" submit-label="Save changes" />
    </div>
</x-app-layout>
