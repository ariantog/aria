<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    /** @use HasFactory<\Database\Factories\WorkerFactory> */
    use HasFactory, SoftDeletes;

    const TYPE_POTONG = 1;

    const TYPE_JAHIT = 2;

    const TYPE_QC = 3;

    protected $guarded = ['id'];

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
}
