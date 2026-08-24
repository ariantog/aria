<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use App\Support\PermissionTableConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(User::getPermissions()['view']);

        $status = $request->query('status', 'active');
        $search = trim((string) $request->query('q', ''));

        $query = User::with(['location', 'roles'])
            ->when($status === 'active', fn ($q) => $q->where('active', true))
            ->when($status === 'banned', fn ($q) => $q->where('active', false))
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search) {
                $sq->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            }));

        return view('users.index', [
            'users' => $query->latest()->paginate(30)->withQueryString(),
            'filters' => ['status' => $status, 'q' => $search],
            'can' => [
                'create_user' => request()->user()?->can(User::getPermissions()['create']) ?? false,
                'edit_user' => request()->user()?->can(User::getPermissions()['edit']) ?? false,
                'edit_role' => request()->user()?->can(User::getPermissions()['roles-edit']) ?? false,
                'edit_location' => request()->user()?->can(Location::getPermissions()['edit']) ?? false,
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
            'role' => ['required', Rule::exists(PermissionTableConfig::rolesTable(), 'name')],
            'location_id' => $this->locationIdValidationRules(),
            'active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
            'location_id' => $this->normalizedLocationId($data['location_id'] ?? null),
            'active' => $data['active'] ?? true,
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
            'role' => ['required', Rule::exists(PermissionTableConfig::rolesTable(), 'name')],
            'location_id' => $this->locationIdValidationRules(),
            'active' => 'boolean',
        ]);

        $updates = [
            'name' => $data['name'],
            'username' => $data['username'],
            'location_id' => $this->normalizedLocationId($data['location_id'] ?? null),
        ];

        if (! $user->is_superadmin && $user->id !== Auth::id()) {
            $updates['active'] = $data['active'] ?? true;
        }

        $user->update($updates);

        if (filled($data['password'] ?? null)) {
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

        if (! $user->active) {
            return redirect()->route('users.index')->with('error', 'User is already banned.');
        }

        $user->update(['active' => false]);

        return redirect()->route('users.index')->with('success', 'User banned.');
    }

    public function unban(User $user)
    {
        Gate::authorize(User::getPermissions()['edit']);

        if ($user->active) {
            return redirect()->route('users.index')->with('error', 'User is already active.');
        }

        $user->update(['active' => true]);

        return redirect()->route('users.index')->with('success', 'User unbanned.');
    }

    /**
     * Legacy production stores "no location restriction" as 0, not NULL.
     * Greenfield SQLite uses NULL (FK to locations does not allow 0).
     */
    private function normalizedLocationId(mixed $locationId): ?int
    {
        $unrestricted = $this->unrestrictedLocationIdValue();

        if ($locationId === null || $locationId === '') {
            return $unrestricted;
        }

        $id = (int) $locationId;

        return $id > 0 ? $id : $unrestricted;
    }

    private function unrestrictedLocationIdValue(): ?int
    {
        return Schema::getConnection()->getDriverName() === 'mysql' ? 0 : null;
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    private function locationIdValidationRules(): array
    {
        return [
            'nullable',
            'integer',
            'min:0',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $id = (int) $value;
                if ($id <= 0) {
                    return;
                }

                if (! Location::whereKey($id)->exists()) {
                    $fail('The selected location is invalid.');
                }
            },
        ];
    }
}
