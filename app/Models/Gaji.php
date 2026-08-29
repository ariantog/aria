<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Gaji extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function getTable()
    {
        static $resolved = null;

        if ($resolved === null) {
            $resolved = Schema::hasTable('gajihs') ? 'gajihs' : 'gajis';
        }

        return $resolved;
    }

    public static function totalColumn(): string
    {
        $table = (new static)->getTable();

        return Schema::hasColumn($table, 'total_gajih') ? 'total_gajih' : 'total_gaji';
    }

    protected function totalGaji(): Attribute
    {
        $column = self::totalColumn();

        return Attribute::make(
            get: fn ($value) => (int) ($value ?? $this->attributes[$column] ?? 0),
            set: function ($value) {
                $intValue = (int) $value;
                if (Schema::hasColumn($this->getTable(), 'total_gajih')) {
                    $this->attributes['total_gajih'] = $intValue;
                }
                if (Schema::hasColumn($this->getTable(), 'total_gaji')) {
                    $this->attributes['total_gaji'] = $intValue;
                }

                return $intValue;
            },
        );
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'id');
    }

    public function getGpuAttribute()
    {
        return (int) $this->bulanan + (int) $this->harian + (int) $this->premi;
    }

    public function bank()
    {
        return $this->belongsTo(Addrbook::class, 'bank_id', 'id');
    }

    public function bankSingle()
    {
        return $this->hasOne(Addrbook::class, 'id', 'bank_id');
    }
}
