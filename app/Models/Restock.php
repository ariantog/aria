<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restock extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function histories()
    {
        return $this->hasMany(RestockHistory::class);
    }
}
