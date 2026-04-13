<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyAccountSummary extends Model
{
    protected $guarded = ['id'];

    public function addrbook()
    {
        return $this->belongsTo(Addrbook::class);
    }
}
