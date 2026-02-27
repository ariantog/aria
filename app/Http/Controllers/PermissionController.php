<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PermissionGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        Gate::authorize(User::getPermissions()['permissions_view']);

        $permissions = Permission::latest()->paginate(50);

        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions,
        ]);
    }

    public function generate(Request $request, PermissionGenerator $generator)
    {
        Gate::authorize(User::getPermissions()['permissions_generate']);

        $request->validate([
            'module_name' => 'required|string|max:255',
        ]);

        try {
            $generator->generateForModule($request->module_name);

            return back()->with('success', 'Permissions generated or updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
