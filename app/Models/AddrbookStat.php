<?php

namespace App\Models;

use App\Models\Concerns\MapsProductionColumns;
use App\Models\Concerns\UsesProductionTable;
use App\Support\ProductionSchema;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddrbookStat extends Model
{
    use HasFactory, MapsProductionColumns, UsesProductionTable;

    protected $table = 'addrbook_stats';

    protected $guarded = [];

    protected static function productionTableKey(): string
    {
        return 'addrbook_stat';
    }

    protected static function productionColumnKey(): string
    {
        return 'addrbook_stat';
    }

    public function getKeyName(): string
    {
        return ProductionSchema::enabled() ? 'customer_id' : 'id';
    }

    public function getIncrementing(): bool
    {
        return ! ProductionSchema::enabled();
    }

    public function addrbook()
    {
        $foreignKey = ProductionSchema::column('addrbook_stat', 'addrbook_id');

        return $this->belongsTo(Addrbook::class, $foreignKey);
    }
}
