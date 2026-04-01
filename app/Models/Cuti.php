<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cuti extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public static $types = array(
		1 => 'Tahunan',
		2 => 'Sakit',
		3 => 'Mendadak',
	);

    public function getTypeNameAttribute()
	{
		return self::$types[$this->tipe] ?? '-';
	}

    public function getTotalCutiAttribute()
	{
        if($this->tipe == 1){
            $tc = $this->tahunan;
        }elseif($this->tipe == 2){
            $tc = $this->sakit;
        }elseif($this->tipe == 3){
            $tc = $this->mendadak;
        }else{
            $tc = 0;
        }

		return $tc;
	}

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }
}
