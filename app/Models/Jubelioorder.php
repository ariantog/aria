<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Jubelioorder extends Model
{
    /** @use HasFactory<\Database\Factories\JubelioorderFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the user that executed the order.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'execute_by');
    }

    /**
     * Get the transaction associated with the jubelio order.
     */
    public function trx(): HasOne
    {
        return $this->hasOne(Transaction::class, 'invoice_number', 'invoice');
    }
}
