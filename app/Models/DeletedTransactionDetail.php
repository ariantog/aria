<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Model;

class DeletedTransactionDetail extends Model
{
    use FillsProductionColumnDefaults;

    protected $table = 'deleted_details';

    public $incrementing = false;

    /**
     * Production `deleted_details` is a copy of `transaction_details` and has
     * no created_at / updated_at. Eloquent timestamps would 1054 on MySQL.
     */
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(DeletedTransaction::class, 'transaction_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
