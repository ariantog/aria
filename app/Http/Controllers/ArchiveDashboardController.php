<?php

namespace App\Http\Controllers;

use App\Models\DataRetentionRun;
use App\Services\DataRetentionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ArchiveDashboardController extends Controller
{
    public function index(DataRetentionService $retention): View
    {
        Gate::authorize(DataRetentionRun::getPermissions()['archive-view']);

        $archiveYears = $retention->archiveTransactionYears();

        return view('archive.index', [
            'archiveConfigured' => $retention->archiveConfigured(),
            'archiveDriver' => $retention->archiveDriver(),
            'retentionYears' => $retention->retentionYears(),
            'liveStartYear' => $retention->liveRetentionStartYear(),
            'archiveYears' => $archiveYears,
            'archiveYearRange' => $archiveYears === []
                ? null
                : [min($archiveYears), max($archiveYears)],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
