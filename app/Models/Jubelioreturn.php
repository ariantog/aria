<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Jubelioreturn extends Model
{
    /** @use HasFactory<\Database\Factories\JubelioreturnFactory> */
    use HasFactory;

    protected $guarded = [];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'confirmed_by');
    }
}
