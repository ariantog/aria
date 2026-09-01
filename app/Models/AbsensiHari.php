<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiHari extends Model
{
    protected $table = 'absensi_hari';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jam' => 'float',
            'incomplete' => 'boolean',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(AbsensiImport::class, 'import_id');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function isMatched(): bool
    {
        return $this->karyawan_id !== null;
    }
}
