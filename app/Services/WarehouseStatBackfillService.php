<?php

namespace App\Services;

use App\Models\WarehouseStatBackfill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Walks the historical months of warehouse stats in resumable batches.
 *
 * Recent activity is already kept current by WarehouseItemStatsRecorder on the
 * queued transaction summary job, so this only has to chip away at the archive.
 * It runs newest month first, because the arrangement report only looks back a
 * year — the most useful periods land first.
 */
class WarehouseStatBackfillService
{
    public const DEFAULT_BATCH_MONTHS = 3;

    /** Stop a cron-driven batch before shared-hosting PHP timeouts (seconds). */
    public const CRON_MAX_SECONDS = 50;

    public function __construct(private readonly WarehouseItemStatsRebuilder $rebuilder) {}

    public function state(): WarehouseStatBackfill
    {
        $state = WarehouseStatBackfill::query()->orderBy('id')->first();

        if (! $state) {
            $state = WarehouseStatBackfill::create(['status' => WarehouseStatBackfill::STATUS_IDLE]);
        }

        return $state;
    }

    /**
     * (Re)start the backfill from the newest month with data back to the oldest.
     *
     * @param  CarbonImmutable|null  $since  Optional floor (e.g. 2026-01-01); does not walk earlier months.
     */
    public function start(?CarbonImmutable $since = null): WarehouseStatBackfill
    {
        $state = $this->state();
        $bounds = $this->rebuilder->periodBounds();

        if ($bounds === null) {
            $state->update([
                'status' => WarehouseStatBackfill::STATUS_COMPLETED,
                'months_total' => 0,
                'months_done' => 0,
                'rows_written' => 0,
                'last_error' => null,
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            return $state->refresh();
        }

        [$oldest, $newest] = $bounds;

        if ($since !== null) {
            $since = $since->startOfMonth();
            if ($since->greaterThan($oldest)) {
                $oldest = $since;
            }
        }

        if ($oldest->greaterThan($newest)) {
            $state->update([
                'status' => WarehouseStatBackfill::STATUS_COMPLETED,
                'months_total' => 0,
                'months_done' => 0,
                'rows_written' => 0,
                'last_error' => null,
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            return $state->refresh();
        }

        $oldestKey = WarehouseItemStatsRebuilder::periodKey($oldest);
        $newestKey = WarehouseItemStatsRebuilder::periodKey($newest);

        $state->update([
            'status' => WarehouseStatBackfill::STATUS_RUNNING,
            'oldest_period' => $oldestKey,
            'newest_period' => $newestKey,
            'cursor_period' => $newestKey,
            'months_total' => $newestKey - $oldestKey + 1,
            'months_done' => 0,
            'rows_written' => 0,
            'last_error' => null,
            'started_at' => now(),
            'finished_at' => null,
            'last_run_at' => null,
        ]);

        return $state->refresh();
    }

    public function pause(): WarehouseStatBackfill
    {
        $state = $this->state();

        if ($state->isRunning()) {
            $state->update(['status' => WarehouseStatBackfill::STATUS_PAUSED]);
        }

        return $state->refresh();
    }

    public function resume(): WarehouseStatBackfill
    {
        $state = $this->state();

        if ($state->status === WarehouseStatBackfill::STATUS_PAUSED) {
            $state->update(['status' => WarehouseStatBackfill::STATUS_RUNNING, 'last_error' => null]);
        }

        return $state->refresh();
    }

    /**
     * Process the next batch of months. Returns the months actually rebuilt.
     *
     * @return array{months: int, rows: int, from: ?string, to: ?string, status: string}
     */
    public function runBatch(?int $months = null, ?int $maxSeconds = null): array
    {
        DB::connection()->disableQueryLog();

        $months = max(1, $months ?? self::DEFAULT_BATCH_MONTHS);
        $deadline = $maxSeconds !== null ? microtime(true) + max(1, $maxSeconds) : null;
        $state = $this->state();

        if (! $state->isRunning()) {
            return [
                'months' => 0,
                'rows' => 0,
                'from' => null,
                'to' => null,
                'status' => $state->status,
            ];
        }

        $cursor = $state->cursor_period;
        $oldest = $state->oldest_period;

        if ($cursor < $oldest) {
            return $this->markCompleted($state);
        }

        $processed = 0;
        $rows = 0;
        $firstPeriod = null;
        $lastPeriod = null;

        for ($i = 0; $i < $months && $cursor >= $oldest; $i++) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            $period = WarehouseItemStatsRebuilder::periodFromKey($cursor);

            try {
                $rows += $this->rebuilder->rebuildMonth($period);
            } catch (Throwable $e) {
                $state->update([
                    'status' => WarehouseStatBackfill::STATUS_FAILED,
                    'last_error' => $e->getMessage(),
                    'last_run_at' => now(),
                    'cursor_period' => $cursor,
                    'months_done' => $state->months_done + $processed,
                    'rows_written' => $state->rows_written + $rows,
                ]);

                throw $e;
            }

            $firstPeriod ??= $period->format('Y-m');
            $lastPeriod = $period->format('Y-m');

            $processed++;
            $cursor--;
        }

        $state->update([
            'cursor_period' => $cursor,
            'months_done' => $state->months_done + $processed,
            'rows_written' => $state->rows_written + $rows,
            'last_run_at' => now(),
            'last_error' => null,
        ]);

        $state->refresh();

        if ($cursor < $oldest) {
            $completed = $this->markCompleted($state);

            return [
                'months' => $processed,
                'rows' => $rows,
                'from' => $firstPeriod,
                'to' => $lastPeriod,
                'status' => $completed['status'],
            ];
        }

        return [
            'months' => $processed,
            'rows' => $rows,
            'from' => $firstPeriod,
            'to' => $lastPeriod,
            'status' => $state->status,
        ];
    }

    /**
     * @return array{months: int, rows: int, from: null, to: null, status: string}
     */
    private function markCompleted(WarehouseStatBackfill $state): array
    {
        $state->update([
            'status' => WarehouseStatBackfill::STATUS_COMPLETED,
            'finished_at' => now(),
            'last_error' => null,
        ]);

        return [
            'months' => 0,
            'rows' => 0,
            'from' => null,
            'to' => null,
            'status' => WarehouseStatBackfill::STATUS_COMPLETED,
        ];
    }

    /**
     * Months still queued behind the cursor.
     */
    public function remainingMonths(WarehouseStatBackfill $state): int
    {
        if (! in_array($state->status, [WarehouseStatBackfill::STATUS_RUNNING, WarehouseStatBackfill::STATUS_PAUSED, WarehouseStatBackfill::STATUS_FAILED], true)) {
            return 0;
        }

        return max(0, $state->cursor_period - $state->oldest_period + 1);
    }
}
