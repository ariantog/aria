<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Karyawan extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function gaji()
    {
        return $this->hasMany(Gaji::class);
    }

    public function gajiSingle()
    {
        return $this->hasOne(Gaji::class)->where('bulan', now()->month)->where('tahun', now()->year);
    }

    public function cuti()
    {
        return $this->hasMany(Cuti::class);
    }

    public function bank()
    {
        return $this->belongsTo(Addrbook::class, 'bank_id');
    }
}
