<x-app-layout title="User management">
    <div class="space-y-6">
        <x-page-header
            title="User management"
            subtitle="Administrator and operator accounts with access to the monitoring dashboard.">
            <x-slot name="actions">
                <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Add user
                </a>
            </x-slot>
        </x-page-header>

        <form method="GET" action="{{ route('users.index') }}" class="panel p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email" class="form-input">
                </div>
                <div class="w-full sm:w-44">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-input">
                        <option value="">All roles</option>
                        <option value="ADMINISTRATOR" @selected(($filters['role'] ?? '') === 'ADMINISTRATOR')>Administrator</option>
                        <option value="OPERATOR" @selected(($filters['role'] ?? '') === 'OPERATOR')>Operator</option>
                    </select>
                </div>
                <div class="w-full sm:w-40">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="">All statuses</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Reset</a>
                </div>
            </div>
        </form>

        <section class="panel overflow-hidden">
            @if ($users->isEmpty())
                <div class="p-6">
                    <x-empty-state title="No users match these filters" message="Adjust the search or add a new operator account." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last sign-in</th>
                                <th>Created</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-600">{{ $user->initials() }}</span>
                                            <div>
                                                <p class="font-medium text-slate-800">{{ $user->name }} @if ($user->id === auth()->id())<span class="text-[10px] text-slate-400">(you)</span>@endif</p>
                                                <p class="text-[11px] text-slate-400">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $user->role->label() }}</span>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $user->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $user->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-slate-500">{{ $user->last_login_at ? display_datetime($user->last_login_at) : 'Never' }}</td>
                                    <td class="text-slate-500">{{ display_date($user->created_at) }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('users.edit', $user) }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Edit</a>
                                            @if ($user->id !== auth()->id())
                                                <x-confirm-form
                                                    :action="route('users.status', $user)"
                                                    method="PATCH"
                                                    :title="$user->is_active ? 'Deactivate user?' : 'Activate user?'"
                                                    :message="$user->is_active
                                                        ? 'The user will be signed out on their next request and blocked from signing in.'
                                                        : 'The user will be able to sign in again with their existing password.'"
                                                    :confirm-label="$user->is_active ? 'Deactivate' : 'Activate'"
                                                    :label="$user->is_active ? 'Deactivate' : 'Activate'"
                                                    :variant="$user->is_active ? 'danger' : 'neutral'" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-4 py-3">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
