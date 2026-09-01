<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaryawanCutiSisaLog extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CUTI = 'cuti';

    protected $table = 'karyawan_cuti_sisa_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'sisa_tahunan_lama' => 'integer',
            'sisa_tahunan_baru' => 'integer',
            'sisa_sakit_lama' => 'integer',
            'sisa_sakit_baru' => 'integer',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editorName(): string
    {
        if (! $this->user) {
            return 'Sistem';
        }

        return $this->user->name ?: ($this->user->username ?: 'User #'.$this->user->id);
    }

    public function sourceLabel(): string
    {
        return match ($this->sumber) {
            self::SOURCE_CUTI => 'Cuti baru / ubah / hapus',
            default => 'Edit sisa',
        };
    }
}
