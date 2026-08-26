<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\WarehouseItemMonthlyStat;
use App\Models\WarehouseStatBackfill;
use App\Services\WarehouseItemStatsRebuilder;
use App\Services\WarehouseStatBackfillService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class WarehouseStatBackfillController extends Controller
{
    private const MAX_MANUAL_BATCH = 12;

    public function index(WarehouseStatBackfillService $backfill, WarehouseItemStatsRebuilder $rebuilder): View
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $state = $backfill->state();
        $bounds = $rebuilder->periodBounds();

        return view('system-settings.warehouse-stat-backfill', [
            'state' => $state,
            'remainingMonths' => $backfill->remainingMonths($state),
            'nextPeriod' => $this->nextPeriodLabel($state),
            'coverage' => $this->coverage(),
            'historyStart' => $bounds[0]?->format('Y-m'),
            'historyEnd' => $bounds[1]?->format('Y-m'),
            'defaultBatch' => WarehouseStatBackfillService::DEFAULT_BATCH_MONTHS,
            'maxManualBatch' => self::MAX_MANUAL_BATCH,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function start(WarehouseStatBackfillService $backfill): RedirectResponse
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $state = $backfill->start();

        if ($state->months_total === 0) {
            return back()->with('error', 'No sell or return transaction details found, so there is nothing to backfill.');
        }

        return back()->with('success', sprintf(
            'Backfill started: %d month(s) queued, newest first. The hourly cron will work through them.',
            $state->months_total,
        ));
    }

    public function pause(WarehouseStatBackfillService $backfill): RedirectResponse
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $backfill->pause();

        return back()->with('success', 'Backfill paused. Progress is kept, resume when ready.');
    }

    public function resume(WarehouseStatBackfillService $backfill): RedirectResponse
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $backfill->resume();

        return back()->with('success', 'Backfill resumed.');
    }

    public function runBatch(Request $request, WarehouseStatBackfillService $backfill): RedirectResponse
    {
        Gate::authorize(Report::getPermissions()['view-warehouse-arrangement']);

        $validated = $request->validate([
            'months' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_MANUAL_BATCH],
        ]);

        $state = $backfill->state();

        if (! $state->isRunning()) {
            return back()->with('error', sprintf('Backfill is %s. Start or resume it first.', $state->status));
        }

        try {
            $result = $backfill->runBatch($validated['months'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', 'Batch failed: '.$e->getMessage());
        }

        if ($result['months'] === 0) {
            return back()->with('success', 'Nothing left to backfill.');
        }

        return back()->with('success', sprintf(
            'Rebuilt %d month(s) (%s → %s), %d stat row(s) written.',
            $result['months'],
            $result['to'],
            $result['from'],
            $result['rows'],
        ));
    }

    private function nextPeriodLabel(WarehouseStatBackfill $state): ?string
    {
        if (! in_array($state->status, [WarehouseStatBackfill::STATUS_RUNNING, WarehouseStatBackfill::STATUS_PAUSED, WarehouseStatBackfill::STATUS_FAILED], true)) {
            return null;
        }

        if ($state->cursor_period < $state->oldest_period) {
            return null;
        }

        return WarehouseItemStatsRebuilder::periodFromKey($state->cursor_period)->format('Y-m');
    }

    /**
     * @return array{rows: int, periods: int, earliest: ?string, latest: ?string}
     */
    private function coverage(): array
    {
        $rows = (int) WarehouseItemMonthlyStat::query()->count();

        if ($rows === 0) {
            return ['rows' => 0, 'periods' => 0, 'earliest' => null, 'latest' => null];
        }

        $bounds = WarehouseItemMonthlyStat::query()
            ->selectRaw('MIN(year * 12 + month) as min_key, MAX(year * 12 + month) as max_key, COUNT(DISTINCT year * 12 + month) as periods')
            ->first();

        return [
            'rows' => $rows,
            'periods' => (int) ($bounds->periods ?? 0),
            'earliest' => $bounds->min_key ? WarehouseItemStatsRebuilder::periodFromKey((int) $bounds->min_key)->format('Y-m') : null,
            'latest' => $bounds->max_key ? WarehouseItemStatsRebuilder::periodFromKey((int) $bounds->max_key)->format('Y-m') : null,
        ];
    }
}
