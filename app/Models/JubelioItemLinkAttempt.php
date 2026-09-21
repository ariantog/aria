<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JubelioItemLinkAttempt extends Model
{
    public const OUTCOME_LINKED = 'linked';

    public const OUTCOME_NO_MATCH = 'no_match';

    public const OUTCOME_AMBIGUOUS = 'ambiguous';

    public const OUTCOME_API_ERROR = 'api_error';

    public const OUTCOME_SKIPPED = 'skipped';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'matched_jubelio_item_id' => 'integer',
            'candidates_count' => 'integer',
            'http_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
