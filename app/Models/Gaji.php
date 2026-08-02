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
        $data = $this->belongsTo(Karyawan::class, 'karyawan_id', 'id');

        // User ID 1 is the one and only superadmin; other users with a role only see active karyawan.
        if (Auth::user() && ! Auth::user()->is_superadmin && count(Auth::user()->getRoleNames()) > 0) {
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
