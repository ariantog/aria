<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KaryawanCutiSisa extends Model
{
    protected $table = 'karyawan_cuti_sisa';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'sisa_tahunan' => 'integer',
            'sisa_sakit' => 'integer',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(KaryawanCutiSisaLog::class, 'karyawan_id', 'karyawan_id')
            ->where('tahun', $this->tahun);
    }
}
