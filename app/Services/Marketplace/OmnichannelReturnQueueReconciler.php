<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Link an existing RETURN transaction or dismiss a marketplace return row without posting stock.
 */
class OmnichannelReturnQueueReconciler
{
    public function __construct(
        private OmnichannelPostReturnEligibility $eligibility,
        private MarketplaceReturnPostGuard $returnPostGuard,
        private OmnichannelMarketplaceReturnWorkflow $workflow,
    ) {}

    public function dismissColumnsReady(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'return_dismissed_at');
    }

    public function detectExistingReturnTransactionId(MarketplaceChannelOrder $order): ?int
    {
        $sellInvoice = $this->eligibility->resolveSellInvoice($order);
        if ($sellInvoice === '') {
            return null;
        }

        $returnInvoice = $this->eligibility->resolveReturnInvoice($order);

        $transactionId = $this->returnPostGuard->existingReturnForSellTransactionId($sellInvoice);
        if ($transactionId !== null) {
            return $transactionId;
        }

        return $this->returnPostGuard->existingReturnTransactionId($returnInvoice);
    }

    public function isDismissed(MarketplaceChannelOrder $order): bool
    {
        if (! $this->dismissColumnsReady()) {
            return false;
        }

        return $order->return_dismissed_at !== null;
    }

    /**
     * @throws \RuntimeException
     */
    public function linkExistingReturn(MarketplaceChannelOrder $order): int
    {
        if ((int) ($order->posted_return_transaction_id ?? 0) > 0) {
            throw new \RuntimeException('Return sudah ditandai diposting.');
        }

        if ($this->isDismissed($order)) {
            throw new \RuntimeException('Baris return sudah di-skip.');
        }

        $sellInvoice = $this->eligibility->resolveSellInvoice($order);
        $transactionId = $this->detectExistingReturnTransactionId($order);
        if ($transactionId === null) {
            throw new \RuntimeException('Transaksi RETURN untuk invoice jual tidak ditemukan.');
        }

        if (! $this->returnPostGuard->linkedSellExists($sellInvoice)) {
            throw new \RuntimeException('Invoice jual tidak ditemukan di Aria.');
        }

        $this->returnPostGuard->backfillChannelOrderReturnPosted($order, $transactionId);

        $returnInvoice = $this->eligibility->resolveReturnInvoice($order);
        $this->returnPostGuard->reconcileNativeSkipBecauseReturnExists($order->fresh(), $sellInvoice, $returnInvoice);

        return $transactionId;
    }

    /**
     * @throws \RuntimeException
     */
    public function dismiss(MarketplaceChannelOrder $order, User $user, ?string $note = null, bool $allowFromVerified = false): void
    {
        if (! $this->dismissColumnsReady()) {
            throw new \RuntimeException('Kolom skip retur belum dimigrasi.');
        }

        if ((int) ($order->posted_return_transaction_id ?? 0) > 0) {
            throw new \RuntimeException('Return sudah diposting — tidak bisa di-skip.');
        }

        if ($order->return_dismissed_at !== null) {
            return;
        }

        if (! $allowFromVerified && $this->workflow->isPhysicallyVerified($order)) {
            throw new \RuntimeException('Sudah diverifikasi — gunakan Post atau tandai sudah diposting, bukan skip.');
        }

        $order->forceFill([
            'return_dismissed_at' => Carbon::now(),
            'return_dismissed_by' => (int) $user->id,
            'return_dismiss_note' => filled($note) ? mb_substr(trim($note), 0, 255) : null,
        ])->save();
    }

    /**
     * @return array{scanned: int, linked: int, dismissed: int, skipped: int, errors: list<string>}
     */
    public function reconcileBatch(bool $apply, bool $dismissOldOutsideWindow, ?int $limit = null): array
    {
        $stats = [
            'scanned' => 0,
            'linked' => 0,
            'dismissed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $query = $this->workflow->listQuery([
            'status' => OmnichannelMarketplaceReturnWorkflow::STATUS_PENDING_VERIFICATION,
            'channel' => 'all',
        ]);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $order) {
            $stats['scanned']++;

            if ((int) ($order->posted_return_transaction_id ?? 0) > 0) {
                $stats['skipped']++;

                continue;
            }

            if ($this->isDismissed($order)) {
                $stats['skipped']++;

                continue;
            }

            $transactionId = $this->detectExistingReturnTransactionId($order);

            if ($transactionId !== null) {
                if ($apply) {
                    try {
                        $this->linkExistingReturn($order);
                        $stats['linked']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = $order->order_ref.': '.$e->getMessage();
                    }
                } else {
                    $stats['linked']++;
                }

                continue;
            }

            if ($dismissOldOutsideWindow && ! $this->eligibility->passesRollingWindow($order)) {
                if ($apply) {
                    try {
                        $systemUser = User::query()->find(1) ?? new User(['id' => 1]);
                        $this->dismiss($order, $systemUser, 'Auto-skip di luar rolling window (reconcile).');
                        $stats['dismissed']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = $order->order_ref.': '.$e->getMessage();
                    }
                } else {
                    $stats['dismissed']++;
                }

                continue;
            }

            $stats['skipped']++;
        }

        return $stats;
    }
}