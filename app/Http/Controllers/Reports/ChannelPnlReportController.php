<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Services\Reporting\ChannelPnlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChannelPnlReportController extends Controller
{
    public function __invoke(Request $request, ChannelPnlService $channelPnl)
    {
        Gate::authorize(Report::getPermissions()['view-channel-pnl']);

        $year = max(
            ChannelPnlService::MIN_YEAR,
            (int) $request->query('year', now()->year),
        );
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $entityId = $this->resolveEntityId($request->query('entity'));

        $isConsolidated = $entityId === null || $entityId === ChannelPnlService::CONSOLIDATED_ENTITY;
        $report = $channelPnl->build($year, $month, $entityId);

        if ($request->query('export') === 'csv') {
            return $channelPnl->exportCsv($report);
        }

        if ($request->query('export') === 'xlsx') {
            return $channelPnl->exportXlsx($report);
        }

        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_pkp']);

        return view('reports.channel-pnl', [
            'entities' => $entities,
            'report' => $report,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'entity' => $isConsolidated ? ChannelPnlService::CONSOLIDATED_ENTITY : $entityId,
            ],
            'yearList' => $channelPnl->yearOptions(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function resolveEntityId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || (int) $raw === ChannelPnlService::CONSOLIDATED_ENTITY) {
            return ChannelPnlService::CONSOLIDATED_ENTITY;
        }

        return (int) $raw;
    }
}
