<?php

namespace App\Models;

use App\Models\Concerns\DisplaysTransactionTotals;
use App\Support\FillsProductionColumnDefaults;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DeletedTransaction extends Model
{
    use DisplaysTransactionTotals;
    use FillsProductionColumnDefaults;

    protected $table = 'deleted';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'due' => 'date',
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'ppn' => 'decimal:2',
        'real_total' => 'decimal:2',
        'total_items' => 'decimal:2',
        'type' => 'integer',
        'submit_type' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(DeletedTransactionDetail::class, 'transaction_id');
    }

    public function sender()
    {
        return $this->belongsTo(Addrbook::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Addrbook::class, 'receiver_id');
    }

    /**
     * Production `deleted` has created_at / updated_at but not deleted_at.
     * Prefer deleted_at when the local/greenfield schema added it.
     */
    public static function archivedAtColumn(): string
    {
        $table = (new static)->getTable();

        foreach (['deleted_at', 'created_at', 'updated_at'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return 'id';
    }

    public function archivedAt(): ?CarbonInterface
    {
        foreach (['deleted_at', 'created_at', 'updated_at'] as $column) {
            $value = $this->getAttributes()[$column] ?? null;
            if ($value) {
                return $this->asDateTime($value);
            }
        }

        return null;
    }
}
