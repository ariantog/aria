<?php

namespace App\Models;

use App\Models\Concerns\UsesProductionTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    /** @use HasFactory<\Database\Factories\WorkerFactory> */
    use HasFactory, SoftDeletes, UsesProductionTable;

    protected $table = 'workers';

    protected static function productionTableKey(): string
    {
        return 'worker';
    }

    const TYPE_POTONG = 1;

    const TYPE_JAHIT = 2;

    const TYPE_QC = 3;

    const TYPE_PRITIL = 4;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
        ];
    }

    public function scopePotong($query)
    {
        return $query->where('type', self::TYPE_POTONG);
    }

    public function scopeJahit($query)
    {
        return $query->where('type', self::TYPE_JAHIT);
    }

    public function scopeQc($query)
    {
        return $query->where('type', self::TYPE_QC);
    }

    public function scopePritil($query)
    {
        return $query->where('type', self::TYPE_PRITIL);
    }

    public static function slugForType(int $type): string
    {
        return match ($type) {
            self::TYPE_POTONG => 'potong',
            self::TYPE_JAHIT => 'jahit',
            self::TYPE_QC => 'qc',
            self::TYPE_PRITIL => 'pritil',
            default => abort(404),
        };
    }

    public function typeSlug(): string
    {
        return self::slugForType($this->type);
    }

    public function foreignKeyColumn(): string
    {
        return match ($this->type) {
            self::TYPE_POTONG => 'potong_id',
            self::TYPE_JAHIT => 'jahit_id',
            self::TYPE_QC => 'qc_id',
            self::TYPE_PRITIL => 'pritil_id',
            default => throw new \InvalidArgumentException('Unknown worker type'),
        };
    }

    public function dateColumn(): string
    {
        return match ($this->type) {
            self::TYPE_POTONG => 'potong_date',
            self::TYPE_JAHIT => 'jahit_date',
            self::TYPE_QC => 'qc_date',
            self::TYPE_PRITIL => 'pritil_date',
            default => throw new \InvalidArgumentException('Unknown worker type'),
        };
    }

    public function produksiRecords(): HasMany
    {
        return $this->hasMany(Produksi::class, $this->foreignKeyColumn());
    }

    public static function getPermissions(): array
    {
        return [
            'view' => 'production-worker-list',
            'create' => 'production-worker-create',
            'edit' => 'production-worker-edit',
            'delete' => 'production-worker-delete',
        ];
    }
}
