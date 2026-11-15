<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JubelioProxyRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'client_name',
        'api_name',
        'params_redacted',
        'jubelio_status',
        'duration_ms',
        'error',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'params_redacted' => 'array',
            'requested_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(JubelioProxyClient::class, 'client_id');
    }
}