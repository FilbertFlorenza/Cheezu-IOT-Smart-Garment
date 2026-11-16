<x-app-layout title="Your profile">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-page-header
            title="Your profile"
            subtitle="Update your display name, email address, and password.">
            <x-slot name="meta">
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $user->role->label() }}</span>
            </x-slot>
        </x-page-header>

        <form method="POST" action="{{ route('profile.update') }}" class="panel p-5 sm:p-6">
            @csrf
            @method('patch')

            <h2 class="text-sm font-semibold text-slate-900">Account information</h2>
            <p class="text-xs text-slate-500">The email address is used to sign in.</p>

            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="form-label">Full name <span class="text-rose-500">*</span></label>
                    <input id="name" name="name" type="text" required maxlength="255"
                           value="{{ old('name', $user->name) }}" class="form-input">
                    @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="form-label">Email address <span class="text-rose-500">*</span></label>
                    <input id="email" name="email" type="email" required maxlength="255"
                           value="{{ old('email', $user->email) }}" class="form-input">
                    @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save changes</button>
            </div>
        </form>

        <form method="POST" action="{{ route('password.update') }}" class="panel p-5 sm:p-6">
            @csrf
            @method('put')

            <h2 class="text-sm font-semibold text-slate-900">Change password</h2>
            <p class="text-xs text-slate-500">Use at least eight characters. You will stay signed in on this device.</p>

            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label for="current_password" class="form-label">Current password <span class="text-rose-500">*</span></label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="form-input">
                    @error('current_password', 'updatePassword') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="form-label">New password <span class="text-rose-500">*</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="form-input">
                    @error('password', 'updatePassword') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Confirm new password <span class="text-rose-500">*</span></label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-input">
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Update password</button>
            </div>
        </form>

        <section class="panel p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-slate-900">Account details</h2>
            <dl class="mt-3 divide-y divide-slate-100 text-sm">
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-slate-500">Role</dt>
                    <dd class="font-medium text-slate-800">{{ $user->role->label() }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-slate-500">Last sign-in</dt>
                    <dd class="font-medium text-slate-800">{{ $user->last_login_at ? display_datetime($user->last_login_at) : 'Never' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-slate-500">Member since</dt>
                    <dd class="font-medium text-slate-800">{{ display_date($user->created_at) }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-slate-400">
                Administrator and operator accounts are managed by an administrator. Contact one to change your role or deactivate an account.
            </p>
        </section>
    </div>
</x-app-layout>
