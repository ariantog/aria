<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddrbookStat extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function addrbook()
    {
        return $this->belongsTo(Addrbook::class);
    }
}
