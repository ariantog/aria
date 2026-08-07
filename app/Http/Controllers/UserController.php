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
        $search = trim((string) $request->query('q', ''));

        $query = User::with(['location', 'roles'])
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'banned', fn ($q) => $q->where('is_active', false))
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search) {
                $sq->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            }));

        return view('users.index', [
            'users' => $query->latest()->paginate(10)->withQueryString(),
            'filters' => ['status' => $status, 'q' => $search],
            'can' => [
                'create_user' => request()->user()?->can(User::getPermissions()['create']) ?? false,
                'edit_user' => request()->user()?->can(User::getPermissions()['edit']) ?? false,
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

        $updates = [
            'name' => $data['name'],
            'username' => $data['username'],
            'location_id' => $data['location_id'] ?? null,
        ];

        if (! $user->is_superadmin && $user->id !== Auth::id()) {
            $updates['is_active'] = $data['is_active'] ?? true;
        }

        $user->update($updates);

        if ($data['password']) {
            $user->update(['password' => bcrypt($data['password'])]);
        }
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function ban(User $user)
    {
        Gate::authorize(User::getPermissions()['edit']);

        if ($user->is_superadmin) {
            return redirect()->route('users.index')->with('error', 'The superadmin account cannot be banned.');
        }

        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')->with('error', 'You cannot ban your own account.');
        }

        if (! $user->is_active) {
            return redirect()->route('users.index')->with('error', 'User is already banned.');
        }

        $user->update(['is_active' => false]);

        return redirect()->route('users.index')->with('success', 'User banned.');
    }

    public function unban(User $user)
    {
        Gate::authorize(User::getPermissions()['edit']);

        if ($user->is_active) {
            return redirect()->route('users.index')->with('error', 'User is already active.');
        }

        $user->update(['is_active' => true]);

        return redirect()->route('users.index')->with('success', 'User unbanned.');
    }
}
