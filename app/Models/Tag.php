<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'item_type',
    ];

    // Constants from legacy logic
    // Constants from legacy logic (Matched to DB)
    const TYPE_TYPE = 3;

    const TYPE_SIZE = 7;

    const TYPE_WARNA = 20;

    const TYPE_JAHIT = 2;

    public static $types = [
        self::TYPE_TYPE => 'Type',
        self::TYPE_SIZE => 'Size',
        self::TYPE_WARNA => 'Warna',
        self::TYPE_JAHIT => 'Jahit',
    ];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_tag');
    }
}
