<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Jubeliosync extends Model
{
    /** @use HasFactory<\Database\Factories\JubeliosyncFactory> */
    use HasFactory;

    protected $guarded = [];

    public function warehouse(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'warehouse_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Addrbook::class, 'id', 'customer_id');
    }
}
