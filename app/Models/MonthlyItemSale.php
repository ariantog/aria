<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyItemSale extends Model
{
    protected $guarded = ['id'];

    public function group()
    {
        return $this->belongsTo(ItemGroup::class, 'group_id');
    }

    public function customer()
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }
}
