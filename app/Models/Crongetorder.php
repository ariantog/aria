<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crongetorder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'date',
            'cek_transaction' => 'boolean',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(Crongetorderdetail::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 0;
    }

    public function progressPercent(): int
    {
        if ($this->total <= 0 || $this->count <= 0) {
            return 0;
        }

        return (int) round(($this->count / $this->total) * 100);
    }

    public function dateRangeIso(): array
    {
        $range = $this->dateRangeCarbon();

        return [
            'from' => $range['from']->utc()->format('Y-m-d\TH:i:s\Z'),
            'to' => $range['to']->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function dateRangeCarbon(): array
    {
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->from)->addDays($this->to)->endOfDay();

        return ['from' => $from, 'to' => $to];
    }

    public function endDateLabel(): string
    {
        return Carbon::parse($this->from)->addDays($this->to)->toDateString();
    }
}
