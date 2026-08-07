<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles, TwoFactorAuthenticatable;

    /**
     * User ID 1 is the one and only superadmin.
     */
    public function getIsSuperadminAttribute(): bool
    {
        return $this->id === 1;
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
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
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
