@props(['user' => null, 'action', 'method' => 'POST', 'submitLabel' => 'Save user'])

<form method="POST" action="{{ $action }}" class="panel p-5 sm:p-6">
    @csrf
    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="name" class="form-label">Full name <span class="text-rose-500">*</span></label>
            <input id="name" name="name" type="text" required maxlength="100" value="{{ old('name', $user?->name ?? '') }}" class="form-input">
            @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="form-label">Email address <span class="text-rose-500">*</span></label>
            <input id="email" name="email" type="email" required maxlength="150" value="{{ old('email', $user?->email ?? '') }}" class="form-input">
            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="role" class="form-label">Role <span class="text-rose-500">*</span></label>
            <select id="role" name="role" class="form-input">
                @foreach (\App\Enums\Role::assignable() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user?->role?->value ?? 'OPERATOR') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Administrators manage users, devices, and integrations. Operators have read-only monitoring access.</p>
            @error('role') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="is_active" class="form-label">Account status <span class="text-rose-500">*</span></label>
            <select id="is_active" name="is_active" class="form-input">
                <option value="1" @selected((string) old('is_active', (int) ($user?->is_active ?? true)) === '1')>Active</option>
                <option value="0" @selected((string) old('is_active', (int) ($user?->is_active ?? true)) === '0')>Inactive</option>
            </select>
            @error('is_active') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="form-label">Password {{ $user ? '(leave blank to keep current)' : '' }} @unless ($user)<span class="text-rose-500">*</span>@endunless</label>
            <input id="password" name="password" type="password" autocomplete="new-password" @required(! $user) class="form-input">
            @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-input">
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
        <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">{{ $submitLabel }}</button>
    </div>
</form>
