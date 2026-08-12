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

    const STATUS_PRODUKSI = 1;

    const STATUS_SETOR = 2;

    const STATUS_BAYAR = 3;

    const STATUS_GUDANG = 5;

    const STATUS_BOTH = 15;

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
