<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoronganDetail extends Model
{
    use HasFactory;

    protected $table = 'prod_borongandetail';

    protected $guarded = ['id'];

    protected $casts = [
        'ongkos' => 'decimal:2',
        'quantity' => 'integer',
        'total' => 'decimal:2',
        'borongan_id' => 'integer',
        'item_id' => 'integer',
        'produksi_id' => 'integer',
    ];

    /**
     * Get the borongan that owns the detail.
     */
    public function borongan(): BelongsTo
    {
        return $this->belongsTo(Borongan::class);
    }

    /**
     * Get the item associated with the detail.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the produksi associated with the detail.
     */
    public function produksi(): BelongsTo
    {
        return $this->belongsTo(Produksi::class);
    }
}
