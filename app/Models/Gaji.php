<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gaji extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'karyawan_gaji';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jam_lembur' => 'float',
            'jam_kerja_aktual' => 'float',
            'jam_kerja_ekspektasi' => 'float',
        ];
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id');
    }

    /**
     * Upah pokok before deductions: bulanan + (26 × harian rate).
     */
    public function getUpahPokokAttribute(): int
    {
        return (int) $this->bulanan + (int) $this->harian;
    }

    public function bank()
    {
        return $this->belongsTo(Addrbook::class, 'bank_id');
    }

    public function bankSingle()
    {
        return $this->hasOne(Addrbook::class, 'id', 'bank_id');
    }
}
