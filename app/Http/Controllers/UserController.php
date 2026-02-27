<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        Gate::authorize(User::getPermissions()['view']);

        return Inertia::render('Users/Index', [
            'users' => User::with('roles', 'location')->latest()->paginate(50),
            'can' => [
                'create_user' => auth()->user()->can(User::getPermissions()['create']),
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize(User::getPermissions()['create']);

        return Inertia::render('Users/Create', [
            'roles' => Role::all(),
            'locations' => Location::all(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(User::getPermissions()['create']);

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'nullable|email|unique:users,email',
            // 'password' => 'required|string|min:8|confirmed', // Password is now optional/auto-generated
            'roles' => 'required|array|min:1',
            'location_id' => 'nullable|exists:locations,id',
            'is_active' => 'boolean',
        ]);

        // Generate a random password if not provided (or always, based on request)
        // User requested: "genere password di backend laravel"
        $password = Str::random(10);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($password),
            'is_active' => $request->boolean('is_active', true),
            'location_id' => $request->location_id,
        ]);

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully. Password: '.$password);
    }

    public function edit(User $user)
    {
        Gate::authorize(User::getPermissions()['edit']);

        return Inertia::render('Users/Edit', [
            'user' => $user->load('roles', 'location'),
            'roles' => Role::all(),
            'userRoles' => $user->roles->pluck('name'),
            'locations' => Location::all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize(User::getPermissions()['edit']);

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'email' => 'required|email|unique:users,email,'.$user->id,
            'roles' => 'array',
            'location_id' => 'nullable|exists:locations,id',
            'is_active' => 'boolean',
            'update_password' => 'boolean',
        ]);

        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'is_active' => $request->boolean('is_active'),
            'location_id' => $request->location_id,
        ];

        $password = null;
        if ($request->boolean('update_password')) {
            $password = Str::random(10);
            $data['password'] = bcrypt($password);
        }

        $user->update($data);

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        $message = 'User updated successfully.';
        if ($password) {
            $message .= ' New Password: '.$password;
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    public function destroy(User $user)
    {
        Gate::authorize(User::getPermissions()['delete']);

        // Prevent deleting self or Super Admin if needed
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot delete yourself.');
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
