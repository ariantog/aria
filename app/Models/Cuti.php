<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cuti extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_TAHUNAN = 1;

    public const TYPE_SAKIT = 2;

    public const TYPE_MENDADAK = 3;

    public const TYPE_IZIN = 4;

    public static array $types = [
        self::TYPE_TAHUNAN => 'Tahunan',
        self::TYPE_SAKIT => 'Sakit',
        self::TYPE_MENDADAK => 'Mendadak',
        self::TYPE_IZIN => 'Izin',
    ];

    public static array $typeStyles = [
        self::TYPE_TAHUNAN => ['Tahunan', 'text-blue-600'],
        self::TYPE_SAKIT => ['Sakit', 'text-orange-600'],
        self::TYPE_MENDADAK => ['Mendadak', 'text-red-600'],
        self::TYPE_IZIN => ['Izin', 'text-amber-700'],
    ];

    protected $guarded = ['id'];

    public function getTypeNameAttribute(): string
    {
        return self::$types[$this->tipe] ?? '-';
    }

    public function getTotalCutiAttribute(): int
    {
        return (int) $this->tahunan + (int) $this->sakit + (int) $this->mendadak + (int) $this->izin;
    }

    public function applyTypeDays(int $days): void
    {
        $this->tahunan = 0;
        $this->sakit = 0;
        $this->mendadak = 0;
        $this->izin = 0;

        match ((int) $this->tipe) {
            self::TYPE_TAHUNAN => $this->tahunan = $days,
            self::TYPE_SAKIT => $this->sakit = $days,
            self::TYPE_MENDADAK => $this->mendadak = $days,
            self::TYPE_IZIN => $this->izin = $days,
            default => null,
        };
    }

    public function daysInMonth(int $year, int $month): int
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $start = Carbon::parse($this->tgl_mulai)->startOfDay();
        $end = Carbon::parse($this->tgl_akhir)->startOfDay();

        $clipStart = $start->greaterThan($monthStart) ? $start : $monthStart;
        $clipEnd = $end->lessThan($monthEnd) ? $end : $monthEnd;

        if ($clipStart->greaterThan($clipEnd)) {
            return 0;
        }

        return (int) $clipStart->diffInDays($clipEnd) + 1;
    }

    public function daysInYear(int $year): int
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->startOfDay();
        $start = Carbon::parse($this->tgl_mulai)->startOfDay();
        $end = Carbon::parse($this->tgl_akhir)->startOfDay();

        $clipStart = $start->greaterThan($yearStart) ? $start : $yearStart;
        $clipEnd = $end->lessThan($yearEnd) ? $end : $yearEnd;

        if ($clipStart->greaterThan($clipEnd)) {
            return 0;
        }

        return (int) $clipStart->diffInDays($clipEnd) + 1;
    }

    public function typeKey(): string
    {
        return match ((int) $this->tipe) {
            self::TYPE_TAHUNAN => 'tahunan',
            self::TYPE_SAKIT => 'sakit',
            self::TYPE_MENDADAK => 'mendadak',
            self::TYPE_IZIN => 'izin',
            default => 'lainnya',
        };
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }
}
