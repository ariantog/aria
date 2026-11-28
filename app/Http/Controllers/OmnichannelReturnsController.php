<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceChannelOrder;
use App\Models\Omnichannel;
use App\Services\Marketplace\OmnichannelMarketplaceReturnWorkflow;
use App\Services\Marketplace\OmnichannelPostReturnEligibility;
use App\Services\Marketplace\OmnichannelReturnOrderPoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OmnichannelReturnsController extends Controller
{
    public function index(
        Request $request,
        OmnichannelMarketplaceReturnWorkflow $workflow,
        OmnichannelPostReturnEligibility $eligibility,
    ): View {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $filters = [
            'channel' => strtolower(trim((string) $request->query('channel', 'all'))),
            'status' => strtolower(trim((string) $request->query('status', OmnichannelMarketplaceReturnWorkflow::STATUS_PENDING_VERIFICATION))),
            'invoice' => trim((string) $request->query('invoice', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'per_page' => max(5, min(100, (int) $request->query('per_page', 20))),
        ];

        if (! in_array($filters['status'], OmnichannelMarketplaceReturnWorkflow::WORKFLOW_STATUSES, true)) {
            $filters['status'] = OmnichannelMarketplaceReturnWorkflow::STATUS_PENDING_VERIFICATION;
        }

        $tableReady = Schema::hasTable('marketplace_channel_orders');
        $rows = null;
        $stats = [];
        $rejectByOrderId = [];

        if ($tableReady) {
            $stats = $workflow->statsCounts();
            $rows = $workflow->listQuery($filters)
                ->paginate($filters['per_page'])
                ->withQueryString();

            if ($workflow->verifyColumnsReady()) {
                $rows->load('returnVerifiedBy:id,username');
            }

            foreach ($rows as $row) {
                $rejectByOrderId[$row->id] = $eligibility->rejectReason($row);
            }
        }

        return view('omnichannel.returns', [
            'filters' => $filters,
            'rows' => $rows,
            'stats' => $stats,
            'tableReady' => $tableReady,
            'verifyReady' => $workflow->verifyColumnsReady(),
            'rejectByOrderId' => $rejectByOrderId,
            'workflow' => $workflow,
            'eligibility' => $eligibility,
        ]);
    }

    public function verify(
        Request $request,
        MarketplaceChannelOrder $marketplaceChannelOrder,
        OmnichannelMarketplaceReturnWorkflow $workflow,
    ): RedirectResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $workflow->verify(
                $marketplaceChannelOrder,
                $request->user(),
                $validated['note'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return '.$marketplaceChannelOrder->order_ref.' diverifikasi — siap posting.');
    }

    public function post(
        Request $request,
        MarketplaceChannelOrder $marketplaceChannelOrder,
        OmnichannelReturnOrderPoster $poster,
        OmnichannelPostReturnEligibility $eligibility,
    ): RedirectResponse {
        abort_unless(Omnichannel::userCanOrders($request->user()), 403);

        if (! $eligibility->masterEnabled()) {
            return back()->with('error', 'OMNICHANNEL_POST_RETURNS belum diaktifkan.');
        }

        $result = $poster->postOrder($marketplaceChannelOrder, false);

        if ($result['posted']) {
            return back()->with('success', 'RETURN diposting untuk '.$marketplaceChannelOrder->order_ref.'.');
        }

        if ($result['skipped']) {
            return back()->with('warning', $result['error'] ?? 'Return dilewati.');
        }

        if ($result['dry_run']) {
            return back()->with('warning', 'Dry-run — tidak ada transaksi dibuat.');
        }

        return back()->with('error', $result['error'] ?? 'Posting gagal.');
    }
}