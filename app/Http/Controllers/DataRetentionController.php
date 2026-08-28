<?php

namespace App\Http\Controllers;

use App\Models\DataRetentionRun;
use App\Services\DataRetentionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class DataRetentionController extends Controller
{
    public function index(DataRetentionService $retention): View
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $runs = DataRetentionRun::query()->orderBy('year')->get();

        return view('system-settings.data-retention', [
            'archiveConfigured' => $retention->archiveConfigured(),
            'retentionYears' => $retention->retentionYears(),
            'liveStartYear' => $retention->liveRetentionStartYear(),
            'eligibleYears' => $retention->yearsEligibleForArchive(),
            'usesPartitioning' => $retention->usesPartitioning(),
            'runs' => $runs,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function previewArchive(Request $request, DataRetentionService $retention): RedirectResponse
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $preview = $retention->previewArchiveYear((int) $validated['year']);

        return back()->with('archive_preview', $preview);
    }

    public function archiveYear(Request $request, DataRetentionService $retention): RedirectResponse
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'confirm' => ['required', 'string'],
        ]);

        $year = (int) $validated['year'];
        $expected = 'ARCHIVE-'.$year;

        if ($validated['confirm'] !== $expected) {
            return back()->with('error', "Type {$expected} to confirm archiving year {$year}.");
        }

        try {
            $result = $retention->archiveYear($year);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Year %d copied to archive: %d transaction(s), %d detail(s), %d customer(s), %d item(s).',
            $year,
            $result['transactions'],
            $result['details'],
            $result['customers'],
            $result['items'],
        ));
    }

    public function previewCleanup(Request $request, DataRetentionService $retention): RedirectResponse
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $preview = $retention->previewLiveCleanup((int) $validated['year']);

        return back()->with('cleanup_preview', $preview);
    }

    public function cleanupYear(Request $request, DataRetentionService $retention): RedirectResponse
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'confirm' => ['required', 'string'],
        ]);

        $year = (int) $validated['year'];
        $expected = 'CLEANUP-'.$year;

        if ($validated['confirm'] !== $expected) {
            return back()->with('error', "Type {$expected} to confirm removing year {$year} from the live database.");
        }

        try {
            $result = $retention->cleanupLiveYear($year);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Year %d removed from live: %d transaction(s), %d detail(s), %d orphan item(s) purged.',
            $year,
            $result['transactions'],
            $result['details'],
            $result['items_purged'],
        ));
    }

    public function purgeOrphanItems(Request $request, DataRetentionService $retention): RedirectResponse
    {
        Gate::authorize(DataRetentionRun::getPermissions()['manage']);

        $validated = $request->validate([
            'confirm' => ['required', 'string', 'in:PURGE-ORPHAN-ITEMS'],
        ]);

        try {
            $purged = $retention->purgeOrphanItemsFromLive(false);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Purged {$purged} orphan item(s) from the live database.");
    }
}
