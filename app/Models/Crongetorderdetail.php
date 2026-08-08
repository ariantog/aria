<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Crongetorderdetail extends Model
{
    protected $guarded = [];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Crongetorder::class, 'crongetorder_id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'invoice_number', 'invoice')
            ->where('type', Transaction::TYPE_SELL);
    }

    public function jubelioOrder(): HasOne
    {
        return $this->hasOne(Jubelioorder::class, 'invoice', 'invoice')
            ->where('type', 'SELL');
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadArray(): array
    {
        $decoded = json_decode($this->payload ?? '', true);

        return is_array($decoded) ? $decoded : [];
    }
}
