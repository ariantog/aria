<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockHistory extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function restock()
    {
        return $this->belongsTo(Restock::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
