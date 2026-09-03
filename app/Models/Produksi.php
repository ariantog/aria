<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produksi extends Model
{
    /** @use HasFactory<\Database\Factories\ProduksiFactory> */
    use HasFactory, SoftDeletes, FillsProductionColumnDefaults;

    protected $table = 'prod_produksi';

    /** @see old/ProduksiModel.php — legacy prod_produksi.status values */
    const STATUS_PRODUKSI = 0;

    const STATUS_SETOR = 1;

    const STATUS_BAYAR = 3;

    const STATUS_GUDANG = 5;

    const STATUS_BOTH = 15;

    /** @return list<int> */
    public static function setoranStatuses(): array
    {
        return [
            self::STATUS_SETOR,
            self::STATUS_BAYAR,
            self::STATUS_GUDANG,
            self::STATUS_BOTH,
        ];
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_PRODUKSI => 'Produksi',
            self::STATUS_SETOR => '-Bayar-Turun',
            self::STATUS_BAYAR => '+Bayar-Turun',
            self::STATUS_GUDANG => '-Bayar+Turun',
            self::STATUS_BOTH => '+Bayar+Turun',
            default => (string) $status,
        };
    }

    /** @return list<array{id: int, name: string}> */
    public static function statusFilterOptions(): array
    {
        return [
            ['id' => self::STATUS_SETOR, 'name' => '-Bayar-Turun'],
            ['id' => self::STATUS_BAYAR, 'name' => '+Bayar-Turun'],
            ['id' => self::STATUS_GUDANG, 'name' => '-Bayar+Turun'],
            ['id' => self::STATUS_BOTH, 'name' => '+Bayar+Turun'],
        ];
    }

    /** @return list<array{id: int, name: string}> */
    public static function reportStatusFilterOptions(): array
    {
        return [
            ['id' => self::STATUS_PRODUKSI, 'name' => self::statusLabel(self::STATUS_PRODUKSI)],
            ...self::statusFilterOptions(),
        ];
    }

    public static function parseStatusFilter(mixed $status): ?int
    {
        if ($status === null || $status === '') {
            return null;
        }

        if (! is_numeric($status)) {
            return null;
        }

        $status = (int) $status;
        $valid = [self::STATUS_PRODUKSI, ...self::setoranStatuses()];

        return in_array($status, $valid, true) ? $status : null;
    }

    public function setoranRowClass(): string
    {
        return match ((int) $this->status) {
            self::STATUS_BAYAR => 'bg-red-100 hover:bg-red-200',
            self::STATUS_GUDANG => 'bg-orange-100 hover:bg-orange-200',
            self::STATUS_BOTH => 'bg-green-100 hover:bg-green-200',
            default => 'hover:bg-gray-50/50',
        };
    }

    public function isInWarehouse(): bool
    {
        return in_array((int) $this->status, [self::STATUS_GUDANG, self::STATUS_BOTH], true);
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'potong_date' => 'date',
            'jahit_date' => 'datetime',
            'qc_date' => 'datetime',
            'pritil_date' => 'datetime',
            'setor_date' => 'datetime',
            'gudang_date' => 'date',
            'status' => 'integer',
        ];
    }

    protected $appends = ['serial'];

    public function serial(): string
    {
        return strtoupper(base_convert($this->id, 10, 36));
    }

    public function getSerialAttribute(): string
    {
        return $this->serial();
    }

    public function potong()
    {
        return $this->belongsTo(Worker::class, 'potong_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function size()
    {
        return $this->belongsTo(Tag::class, 'size_id');
    }

    public function jahit()
    {
        return $this->belongsTo(Worker::class, 'jahit_id');
    }

    public function qc()
    {
        return $this->belongsTo(Worker::class, 'qc_id');
    }

    public function pritil()
    {
        return $this->belongsTo(Worker::class, 'pritil_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function original()
    {
        return $this->belongsTo(self::class, 'original_id');
    }

    public function splits()
    {
        return $this->hasMany(self::class, 'original_id');
    }

    public function parentSerial(): ?string
    {
        if (! $this->original_id) {
            return null;
        }

        return strtoupper(base_convert((string) $this->original_id, 10, 36));
    }

    public static function getPermissions(): array
    {
        return [
            'view' => 'production-list',
            'create' => 'production-create',
            'edit' => 'production-edit',
            'delete' => 'production-delete',
            'setor' => 'production-setor',
            'setoran-view' => 'production-setoran-list',
            'gudang' => 'production-gudang',
            'revert' => 'production-setoran-revert',
        ];
    }
}
