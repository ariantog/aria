<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyInventorySummary extends Model
{
    protected $guarded = ['id'];

    public function warehouse()
    {
        return $this->belongsTo(Addrbook::class, 'warehouse_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
