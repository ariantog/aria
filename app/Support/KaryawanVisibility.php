<?php

namespace App\Support;

use App\Models\Gaji;
use App\Models\Karyawan;
use App\Models\User;

class KaryawanVisibility
{
    public const FLAG_PUBLIC = 1;

    public const FLAG_PRIVATE = 2;

    public static function isSuperadmin(?User $user): bool
    {
        return $user !== null && $user->is_superadmin;
    }

    public static function canViewKaryawan(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isSuperadmin($user)) {
            return true;
        }

        return $user->can(Karyawan::getPermissions()['list']);
    }

    public static function canViewKaryawanRecord(?User $user, Karyawan $karyawan): bool
    {
        if (! self::canViewKaryawan($user)) {
            return false;
        }

        if ((int) $karyawan->flag === self::FLAG_PRIVATE) {
            return self::isSuperadmin($user);
        }

        return true;
    }

    public static function canManageKaryawan(?User $user, ?Karyawan $karyawan = null): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isSuperadmin($user)) {
            return true;
        }

        if ($karyawan && (int) $karyawan->flag === self::FLAG_PRIVATE) {
            return false;
        }

        return $user->can(Karyawan::getPermissions()['create'])
            || $user->can(Karyawan::getPermissions()['edit']);
    }

    public static function canViewGajiList(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isSuperadmin($user)) {
            return true;
        }

        return $user->can(Karyawan::getPermissions()['gaji-list']);
    }

    public static function canViewGajiRecord(?User $user, Gaji $gaji): bool
    {
        if (! self::canViewGajiList($user)) {
            return false;
        }

        if ((int) $gaji->flag === self::FLAG_PRIVATE) {
            return self::isSuperadmin($user);
        }

        $karyawan = $gaji->relationLoaded('karyawan')
            ? $gaji->karyawan
            : $gaji->karyawan()->withoutGlobalScopes()->first();

        if ($karyawan && (int) $karyawan->flag === self::FLAG_PRIVATE) {
            return self::isSuperadmin($user);
        }

        return true;
    }

    public static function canEditGaji(?User $user, Gaji $gaji): bool
    {
        if (! self::canViewGajiRecord($user, $gaji)) {
            return false;
        }

        if (self::isSuperadmin($user)) {
            return true;
        }

        return $user->can(Karyawan::getPermissions()['gaji-edit']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Gaji>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Gaji>
     */
    public static function scopeVisibleGaji($query, ?User $user)
    {
        if (self::isSuperadmin($user)) {
            return $query;
        }

        $query->where('flag', self::FLAG_PUBLIC);

        return $query->whereHas('karyawan', function ($karyawanQuery) {
            $karyawanQuery->where('flag', self::FLAG_PUBLIC);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Karyawan>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Karyawan>
     */
    public static function scopeVisibleKaryawan($query, ?User $user)
    {
        if (self::isSuperadmin($user)) {
            return $query;
        }

        return $query->where('flag', self::FLAG_PUBLIC);
    }
}
