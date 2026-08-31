<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;
    use \Spatie\Permission\Traits\HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
        hasAnyPermission as protected spatieHasAnyPermission;
        hasAllPermissions as protected spatieHasAllPermissions;
        hasRole as protected spatieHasRole;
        hasAnyRole as protected spatieHasAnyRole;
        checkPermissionTo as protected spatieCheckPermissionTo;
    }

    /**
     * The one and only superadmin account. This user bypasses all ACL and location filters.
     */
    public const SUPERADMIN_ID = 1;

    public static function isSuperadmin(?self $user): bool
    {
        return $user !== null && $user->id === self::SUPERADMIN_ID;
    }


    /**
     * User ID 1 is the one and only superadmin.
     */
    public function getIsSuperadminAttribute(): bool
    {
        return self::isSuperadmin($this);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    public function checkPermissionTo($permission, $guardName = null): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        return $this->spatieCheckPermissionTo($permission, $guardName);
    }

    public function hasAnyPermission(...$permissions): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        return $this->spatieHasAnyPermission(...$permissions);
    }

    public function hasAllPermissions(...$permissions): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        return $this->spatieHasAllPermissions(...$permissions);
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        return $this->spatieHasRole($roles, $guard);
    }

    public function hasAnyRole(...$roles): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        return $this->spatieHasAnyRole(...$roles);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'active',
        'location_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    public function staffRoles(): BelongsToMany
    {
        return $this->belongsToMany(StaffRole::class, 'staff_role_user', 'user_id', 'staff_role_id')
            ->withTimestamps()
            ->orderBy('staff_roles.sort_order');
    }

    public static function getPermissions(): array
    {
        return [
            'view' => 'users-list',
            'create' => 'users-create',
            'edit' => 'users-edit',

            // Roles
            'roles-view' => 'users-roles-list',
            'roles-create' => 'users-roles-create',
            'roles-edit' => 'users-roles-edit',
            'roles-delete' => 'users-roles-delete',

            // Permissions
            'permissions-view' => 'users-permissions-list',
            'permissions-generate' => 'users-permissions-generate',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException('Users cannot be deleted. Ban the user instead.');
        });
    }
}
