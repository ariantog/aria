<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokReport extends Model
{
    use HasFactory;

    protected $table = 'stok_reports';

    protected $fillable = [
        'generet_at',
        'type',
        'generet_by',
    ];

    protected function casts(): array
    {
        return [
            'generet_at' => 'datetime',
        ];
    }

    public function stockData(): HasMany
    {
        return $this->hasMany(StockData::class, 'id_stock_report');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generet_by');
    }
}
