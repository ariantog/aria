<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OmnichannelPostSellStat extends Model
{
    public const ROW_ID = 1;

    public $incrementing = false;

    protected $table = 'omnichannel_post_sell_stats';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

  /** @var list<string> */
    protected $fillable = [
        'id',
        'scope_key',
        'posted',
        'eligible_to_post',
        'errors',
        'updated_at',
    ];

    protected $casts = [
        'posted' => 'integer',
        'eligible_to_post' => 'integer',
        'errors' => 'integer',
        'updated_at' => 'datetime',
    ];
}