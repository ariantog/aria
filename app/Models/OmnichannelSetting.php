<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OmnichannelSetting extends Model
{
    protected $table = 'omnichannel_settings';

    protected $fillable = [
        'key',
        'value',
    ];
}