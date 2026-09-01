<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Karyawan extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public static function getPermissions(): array
    {
        return [
            // Karyawan permissions
            'view' => 'karyawan-view',
            'list' => 'karyawan-list',
            'create' => 'karyawan-create',
            'edit' => 'karyawan-edit',
            'delete' => 'karyawan-delete',

            // Gaji (Salary) permissions
            // Gaji
            'gaji-list' => 'karyawan-gaji-list',
            'gaji-create' => 'karyawan-gaji-create',
            'gaji-edit' => 'karyawan-gaji-edit',
            'gaji-delete' => 'karyawan-gaji-delete',

            // Cuti
            'cuti-list' => 'karyawan-cuti-list',
            'cuti-create' => 'karyawan-cuti-create',
            'cuti-edit' => 'karyawan-cuti-edit',
            'cuti-delete' => 'karyawan-cuti-delete',

            // Absensi fingerprint
            'absensi-list' => 'karyawan-absensi-list',
            'absensi-import' => 'karyawan-absensi-import',

            // Hari libur nasional / libur pabrik
            'hari-libur-list' => 'karyawan-hari-libur-list',
            'hari-libur-create' => 'karyawan-hari-libur-create',
            'hari-libur-delete' => 'karyawan-hari-libur-delete',
        ];
    }

    protected function casts(): array
    {
        return [
            'waktu_dibatasi' => 'boolean',
            'jam_kerja' => 'integer',
        ];
    }

    public static function findByAbsenId(?string $absenId): ?self
    {
        $key = mb_strtolower(trim((string) $absenId));
        if ($key === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(absen_id) = ?', [$key])
            ->first();
    }

    public function jamKerjaPerHari(): int
    {
        $hours = (int) ($this->jam_kerja ?? 0);
        if ($hours > 0) {
            return $hours;
        }

        return max(1, (int) Setting::getValue('payroll.jam_kerja_per_hari', 8));
    }

    public function gaji()
    {
        return $this->hasMany(Gaji::class);
    }

    public function gajiSingle()
    {
        return $this->hasOne(Gaji::class)->where('bulan', now()->month)->where('tahun', now()->year);
    }

    public function cuti()
    {
        return $this->hasMany(Cuti::class);
    }

    public function bank()
    {
        return $this->belongsTo(Addrbook::class, 'bank_id');
    }

    public function absensiHari()
    {
        return $this->hasMany(AbsensiHari::class);
    }
}
