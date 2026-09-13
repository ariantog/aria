<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemInsightRanking extends Model
{
    public const CATEGORY_BEST_SELLING = 'best_selling';

    public const CATEGORY_MOST_PROFITABLE = 'most_profitable';

    public const CATEGORY_LOSS_LEADER = 'loss_leader';

    public const CATEGORY_FASTEST_SELLING = 'fastest_selling';

    protected $fillable = [
        'year',
        'month',
        'category',
        'rank',
        'item_id',
        'item_name',
        'item_code',
        'net_qty',
        'net_value',
        'cost_total',
        'profit',
        'margin_pct',
        'daily_velocity',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'rank' => 'integer',
            'item_id' => 'integer',
            'net_qty' => 'float',
            'net_value' => 'float',
            'cost_total' => 'float',
            'profit' => 'float',
            'margin_pct' => 'float',
            'daily_velocity' => 'float',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_BEST_SELLING => 'Best selling',
            self::CATEGORY_MOST_PROFITABLE => 'Most profitable',
            self::CATEGORY_LOSS_LEADER => 'Loss leaders',
            self::CATEGORY_FASTEST_SELLING => 'Fastest selling',
        ];
    }

    /**
     * @return list<string>
     */
    public static function validCategories(): array
    {
        return array_keys(self::categoryLabels());
    }
}
