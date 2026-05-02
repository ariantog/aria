<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Logjubelio extends Model
{
    /** @use HasFactory<\Database\Factories\LogjubelioFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'cron_failed' => 'array',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_solved_by');
    }
}
