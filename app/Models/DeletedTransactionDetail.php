<?php

namespace App\Models;

use App\Models\Concerns\UsesProductionTable;
use Illuminate\Database\Eloquent\Model;

class DeletedTransactionDetail extends Model
{
    use UsesProductionTable;

    protected $table = 'deleted_transaction_details';

    protected static function productionTableKey(): string
    {
        return 'deleted_transaction_detail';
    }

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'deleted_at' => 'datetime',
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
