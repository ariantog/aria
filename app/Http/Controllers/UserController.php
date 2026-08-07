<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(User::getPermissions()['view']);

        $status = $request->query('status', 'active');

        $query = User::with(['location', 'roles'])
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'banned', fn ($q) => $q->where('is_active', false))
            ->when(
                Auth::user()->is_superadmin
                    && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(User::class), true),
                fn ($q) => $q->withTrashed()
            );

        return view('users.index', [
            'users' => $query->latest()->paginate(10)->withQueryString(),
            'filters' => ['status' => $status],
            'can' => [
                'create_user' => request()->user()?->can(User::getPermissions()['create']) ?? false,
                'edit_user' => request()->user()?->can(User::getPermissions()['edit']) ?? false,
                'delete_user' => request()->user()?->can(User::getPermissions()['delete']) ?? false,
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function create()
    {
        Gate::authorize(User::getPermissions()['create']);

        return view('users.create', [
            'locations' => Location::all(),
            'roles' => \Spatie\Permission\Models\Role::all(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(User::getPermissions()['create']);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'location_id' => 'nullable|exists:locations,id',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
            'location_id' => $data['location_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        Gate::authorize(User::getPermissions()['edit']);

        return view('users.edit', [
            'editUser' => $user->load('roles'),
            'userRoles' => $user->roles->pluck('name'),
            'locations' => Location::all(),
            'roles' => \Spatie\Permission\Models\Role::all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize(User::getPermissions()['edit']);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'location_id' => 'nullable|exists:locations,id',
            'is_active' => 'boolean',
        ]);

        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'location_id' => $data['location_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($data['password']) {
            $user->update(['password' => bcrypt($data['password'])]);
        }
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        Gate::authorize(User::getPermissions()['delete']);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }
}
