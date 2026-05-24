<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Borongan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'from' => 'date',
        'to' => 'date',
        'tres' => 'decimal:2',
        'permak' => 'decimal:2',
        'lain2' => 'decimal:2',
        'total' => 'decimal:2',
        'total_items' => 'integer',
        'user_id' => 'integer',
        'jahit_id' => 'integer',
    ];

    /**
     * Get the user that created the borongan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the worker (jahit) associated with the borongan.
     */
    public function jahit(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'jahit_id');
    }

    /**
     * Get the details associated with the borongan.
     */
    public function details(): HasMany
    {
        return $this->hasMany(BoronganDetail::class);
    }

    public static function getPermissions(): array
    {
        return [
            'view' => 'borongan-list',
            'create' => 'borongan-create',
            'view-details' => 'borongan-view',
            'delete' => 'borongan-delete',
        ];
    }
}
