<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandaloneInvoiceLine extends Model
{
    /** @use HasFactory<\Database\Factories\StandaloneInvoiceLineFactory> */
    use HasFactory;

    protected $fillable = [
        'standalone_invoice_id',
        'line_order',
        'description',
        'quantity',
        'price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StandaloneInvoice::class, 'standalone_invoice_id');
    }
}
