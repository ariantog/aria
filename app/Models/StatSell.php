<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatSell extends Model
{
    protected $guarded = ['id'];

    public function group()
    {
        return $this->belongsTo(ItemGroup::class, 'group_id');
    }

    public function sender()
    {
        return $this->belongsTo(Addrbook::class, 'sender_id');
    }

    public function getTypeNameAttribute()
    {
        return $this->type == Transaction::TYPE_SELL ? 'Sell' : 'Return';
    }
}
