<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JubelioStockCheck extends Model
{
    use HasFactory;

    protected $fillable = ['page_tracking', 'status'];

    public function discrepancies(): HasMany
    {
        return $this->hasMany(JubelioStockDiscrepancy::class);
    }

}
