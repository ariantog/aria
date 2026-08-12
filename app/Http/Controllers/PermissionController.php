<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PermissionGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(User::getPermissions()['permissions-view']);

        $search = trim((string) $request->query('search', ''));

        $permissions = Permission::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('permissions.index', [
            'permissions' => $permissions,
            'search' => $search,
            'can' => [
                'generate' => request()->user()?->can(User::getPermissions()['permissions-generate']) ?? false,
            ],
        ]);
    }

    public function generate(Request $request, PermissionGenerator $generator)
    {
        Gate::authorize(User::getPermissions()['permissions-generate']);

        $request->validate([
            'module_name' => 'nullable|string|max:255',
        ]);

        try {
            if ($request->module_name) {
                $generator->generateForModule($request->module_name);
                $message = "Permissions for '{$request->module_name}' generated or updated successfully.";
            } else {
                $generator->generateAll();
                $message = 'All permissions generated or updated successfully.';
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
