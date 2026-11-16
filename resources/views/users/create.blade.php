<x-app-layout title="Add user">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header title="Add user" subtitle="Create an administrator or operator account. Public registration is disabled." />
        <x-users.form :action="route('users.store')" method="POST" submit-label="Create user" />
    </div>
</x-app-layout>
