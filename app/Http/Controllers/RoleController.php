<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionGrouper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        Gate::authorize(User::getPermissions()['roles-view']);

        return view('roles.index', [
            'roles' => Role::with('permissions')->latest()->paginate(50),
            'can' => [
                'create_role' => request()->user()?->can(User::getPermissions()['roles-create']) ?? false,
                'edit_role' => request()->user()?->can(User::getPermissions()['roles-edit']) ?? false,
                'delete_role' => request()->user()?->can(User::getPermissions()['roles-delete']) ?? false,
            ],
        ]);
    }

    public function deleted()
    {
        Gate::authorize(User::getPermissions()['roles-view']);

        return view('roles.deleted', [
            'roles' => Role::onlyTrashed()->with('permissions')->latest('deleted_at')->paginate(50),
            'can' => [
                'restore_role' => request()->user()?->can(User::getPermissions()['roles-delete']) ?? false,
            ],
        ]);
    }

    public function show(Role $role)
    {
        Gate::authorize(User::getPermissions()['roles-view']);

        return view('roles.show', [
            'role' => $role,
            'users' => User::role($role->name)
                ->with('location')
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'can' => [
                'edit_user' => request()->user()?->can(User::getPermissions()['edit']) ?? false,
                'edit_role' => request()->user()?->can(User::getPermissions()['roles-edit']) ?? false,
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize(User::getPermissions()['roles-create']);

        return view('roles.create', [
            'permissions' => $this->getGroupedPermissions(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(User::getPermissions()['roles-create']);

        $request->validate([
            'name' => $this->roleNameRules(),
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

        return view('roles.edit', [
            'role' => $role,
            'permissions' => $this->getGroupedPermissions(),
            'rolePermissions' => $role->permissions->pluck('name'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        Gate::authorize(User::getPermissions()['roles-edit']);

        $request->validate([
            'name' => $this->roleNameRules($role->id),
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

        return back()->with('success', 'Role deleted. You can restore it from Deleted Roles.');
    }

    public function restore(int $role)
    {
        Gate::authorize(User::getPermissions()['roles-delete']);

        $role = Role::onlyTrashed()->findOrFail($role);
        $role->restore();

        return redirect()->route('roles.deleted.index')->with('success', 'Role restored successfully.');
    }

    private function roleNameRules(?int $ignoreId = null): array
    {
        $rule = Rule::unique(config('permission.table_names.roles'), 'name')
            ->where(fn ($query) => $query->where('guard_name', 'web')->whereNull('deleted_at'));

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return ['required', 'string', $rule];
    }

    private function getGroupedPermissions(): array
    {
        return PermissionGrouper::group(
            \Spatie\Permission\Models\Permission::query()->orderBy('name')->get()
        );
    }
}
