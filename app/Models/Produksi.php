<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produksi extends Model
{
    /** @use HasFactory<\Database\Factories\ProduksiFactory> */
    use HasFactory, SoftDeletes;

    const STATUS_PRODUKSI = 1;

    const STATUS_SETOR = 2;

    const STATUS_BAYAR = 3;

    const STATUS_GUDANG = 5;

    const STATUS_BOTH = 15;

    protected $guarded = ['id'];

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
}
