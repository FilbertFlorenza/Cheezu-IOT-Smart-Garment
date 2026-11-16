<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreUserRequest;
use App\Http\Requests\Web\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:ADMINISTRATOR,OPERATOR'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->staff()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->validated());

        return redirect()
            ->route('users.index')
            ->with('status', 'User '.$user->name.' created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $this->guardAdministratorAccess($user, $data['role'], (bool) $data['is_active']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('status', 'User '.$user->name.' updated.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->guardAdministratorAccess(
            $user,
            $user->role->value,
            ! $user->is_active,
        );

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()
            ->back()
            ->with('status', 'User '.$user->name.' '.($user->is_active ? 'activated' : 'deactivated').'.');
    }

    private function guardAdministratorAccess(User $target, string $newRole, bool $newActive): void
    {
        if (! $target->isAdministrator() || ($newRole === Role::Administrator->value && $newActive)) {
            return;
        }

        if ($target->id === auth()->id()) {
            throw ValidationException::withMessages([
                'role' => 'You cannot deactivate or demote your own administrator account.',
            ]);
        }

        $otherActiveAdmins = User::query()
            ->where('role', Role::Administrator)
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->exists();

        if (! $otherActiveAdmins) {
            throw ValidationException::withMessages([
                'role' => 'At least one active administrator must remain.',
            ]);
        }
    }
}
