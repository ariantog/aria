<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Gaji extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function karyawan()
    {
        $role = Auth::user() && count(Auth::user()->getRoleNames()) > 0 ? Auth::user()->getRoleNames()[0] : null;

        $data = $this->belongsTo(Karyawan::class, 'karyawan_id', 'id');

        if ($role != 'superadmin' && $role != null) {
            $data = $data->where('flag', 1);
        }

        return $data;
    }

    public function getGpuAttribute()
    {
        $gpuTotal = $this->bulanan + $this->harian + $this->premi;

        return $gpuTotal;
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
