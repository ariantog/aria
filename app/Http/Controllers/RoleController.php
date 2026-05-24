<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        Gate::authorize(User::getPermissions()['roles-view']);

        return Inertia::render('Roles/Index', [
            'roles' => Role::with('permissions')->latest()->paginate(50),
        ]);
    }

    public function create()
    {
        Gate::authorize(User::getPermissions()['roles-create']);

        return Inertia::render('Roles/Create', [
            'permissions' => $this->getGroupedPermissions(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(User::getPermissions()['roles-create']);

        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        Gate::authorize(User::getPermissions()['roles-edit']);

        $role->load('permissions');

        return Inertia::render('Roles/Edit', [
            'role' => $role,
            'permissions' => $this->getGroupedPermissions(),
            'rolePermissions' => $role->permissions->pluck('name'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        Gate::authorize(User::getPermissions()['roles-edit']);

        $request->validate([
            'name' => 'required|string|unique:roles,name,'.$role->id,
            'permissions' => 'array',
        ]);

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        Gate::authorize(User::getPermissions()['roles-delete']);

        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }

    private function getGroupedPermissions()
    {
        $permissions = Permission::all();
        $grouped = [];

        foreach ($permissions as $permission) {
            $name = $permission->name;

            // Priority 1: Underscore (assetLancar_view -> assetLancar)
            if (str_contains($name, '_')) {
                $module = explode('_', $name)[0];
            }
            // Priority 2: Hyphen (asset-lancar-create -> asset)
            elseif (str_contains($name, '-')) {
                $module = explode('-', $name)[0];
            } else {
                $module = 'general';
            }

            if (! isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }

        return $grouped;
    }
}
