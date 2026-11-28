<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceChannelOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Physical-verify workflow for Shopee / TikTok / Tokopedia native returns (not Jubelio queue).
 */
class OmnichannelMarketplaceReturnWorkflow
{
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_POSTED = 'posted';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ALL = 'all';

    /** @var list<string> */
    public const WORKFLOW_STATUSES = [
        self::STATUS_ALL,
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_VERIFIED,
        self::STATUS_POSTED,
        self::STATUS_DISMISSED,
    ];

    public function __construct(
        private OmnichannelPostReturnEligibility $eligibility,
        private MarketplaceChannelOrderOpsBucket $opsBucket,
    ) {}

    public function requirePhysicalVerify(): bool
    {
        return (bool) config('marketplace.post_return.require_physical_verify', true);
    }

    public function verifyColumnsReady(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'return_verified_at');
    }

    public function isPhysicallyVerified(MarketplaceChannelOrder $order): bool
    {
        if (! $this->verifyColumnsReady()) {
            return ! $this->requirePhysicalVerify();
        }

        return $order->return_verified_at !== null;
    }

    public function dismissColumnsReady(): bool
    {
        return Schema::hasTable('marketplace_channel_orders')
            && Schema::hasColumn('marketplace_channel_orders', 'return_dismissed_at');
    }

    public function isDismissed(MarketplaceChannelOrder $order): bool
    {
        if (! $this->dismissColumnsReady()) {
            return false;
        }

        return $order->return_dismissed_at !== null;
    }

    public function workflowStatus(MarketplaceChannelOrder $order): string
    {
        if (Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')
            && (int) ($order->posted_return_transaction_id ?? 0) > 0) {
            return self::STATUS_POSTED;
        }

        if ($this->isDismissed($order)) {
            return self::STATUS_DISMISSED;
        }

        if ($this->isPhysicallyVerified($order)) {
            return self::STATUS_VERIFIED;
        }

        if ($this->hasReturnSignal($order)) {
            return self::STATUS_PENDING_VERIFICATION;
        }

        return self::STATUS_PENDING_VERIFICATION;
    }

    public function hasReturnSignal(MarketplaceChannelOrder $order): bool
    {
        if (! $this->eligibility->passesChannelAllowlist($order)) {
            return false;
        }

        if (! $this->eligibility->passesRollingWindow($order)) {
            return false;
        }

        return $this->eligibility->passesReturnGate($order);
    }

    public function verify(MarketplaceChannelOrder $order, User $user, ?string $note = null): void
    {
        if (! $this->verifyColumnsReady()) {
            throw new \RuntimeException('Kolom verifikasi retur belum dimigrasi.');
        }

        if (! $this->hasReturnSignal($order)) {
            throw new \RuntimeException('Order belum memenuhi sinyal return marketplace.');
        }

        if ((int) ($order->posted_return_transaction_id ?? 0) > 0) {
            throw new \RuntimeException('Return sudah diposting.');
        }

        if ($this->isDismissed($order)) {
            throw new \RuntimeException('Return sudah di-skip.');
        }

        if ($order->return_verified_at !== null) {
            return;
        }

        $order->forceFill([
            'return_verified_at' => Carbon::now(),
            'return_verified_by' => (int) $user->id,
            'return_verify_note' => filled($note) ? mb_substr(trim($note), 0, 255) : null,
        ])->save();
    }

    /**
     * @param  array{channel?: string, status?: string, invoice?: string, date_from?: string, date_to?: string, per_page?: int}  $filters
     */
    public function listQuery(array $filters): Builder
    {
        $query = MarketplaceChannelOrder::query()
            ->whereIn('channel', [MarketplaceWebhookRecorder::CHANNEL_SHOPEE, MarketplaceWebhookRecorder::CHANNEL_TIKTOK]);

        $this->applyNativeChannelScope($query, $filters['channel'] ?? 'all');
        $this->applyReturnSignalScope($query);
        $this->applyWorkflowStatusFilter($query, $filters['status'] ?? self::STATUS_PENDING_VERIFICATION);

        $invoice = trim((string) ($filters['invoice'] ?? ''));
        if ($invoice !== '') {
            $query->where(function (Builder $inner) use ($invoice) {
                $inner->where('order_ref', 'like', '%'.$invoice.'%');
                if (ctype_digit($invoice)) {
                    $inner->orWhere('id', (int) $invoice);
                }
            });
        }

        $from = trim((string) ($filters['date_from'] ?? ''));
        if ($from !== '') {
            $query->where(function (Builder $inner) use ($from) {
                $inner->whereDate('marketplace_ordered_at', '>=', $from)
                    ->orWhereDate('last_event_at', '>=', $from);
            });
        }

        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($to !== '') {
            $query->where(function (Builder $inner) use ($to) {
                $inner->whereDate('marketplace_ordered_at', '<=', $to)
                    ->orWhereDate('last_event_at', '<=', $to);
            });
        }

        return $query->orderByDesc('last_event_at')->orderByDesc('id');
    }

    /**
     * @return array<string, int>
     */
    public function statsCounts(): array
    {
        $base = MarketplaceChannelOrder::query()
            ->whereIn('channel', [MarketplaceWebhookRecorder::CHANNEL_SHOPEE, MarketplaceWebhookRecorder::CHANNEL_TIKTOK]);
        $this->applyNativeChannelScope($base, 'all');
        $this->applyReturnSignalScope($base);

        $counts = [];
        foreach ([self::STATUS_PENDING_VERIFICATION, self::STATUS_VERIFIED, self::STATUS_POSTED, self::STATUS_DISMISSED] as $status) {
            $scoped = clone $base;
            $this->applyWorkflowStatusFilter($scoped, $status);
            $counts[$status] = $scoped->count();
        }
        $counts[self::STATUS_ALL] = (clone $base)->count();

        return $counts;
    }

    private function applyNativeChannelScope(Builder $query, string $channelFilter): void
    {
        $channelFilter = strtolower(trim($channelFilter));
        if ($channelFilter === '' || $channelFilter === 'all') {
            $allowed = $this->eligibility->allowedChannels();
            $query->where(function (Builder $inner) use ($allowed) {
                if (in_array('shopee', $allowed, true)) {
                    $inner->orWhere('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE);
                }
                if (in_array('tiktok', $allowed, true)) {
                    $inner->orWhere(function (Builder $tiktok) {
                        $tiktok->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                            ->where(function (Builder $plat) {
                                $plat->whereNull('commerce_platform')
                                    ->orWhere('commerce_platform', '')
                                    ->orWhereRaw('UPPER(commerce_platform) != ?', ['TOKOPEDIA']);
                            });
                    });
                }
                if (in_array('tokopedia', $allowed, true)) {
                    $inner->orWhere(function (Builder $tok) {
                        $tok->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                            ->whereRaw('UPPER(COALESCE(commerce_platform, "")) = ?', ['TOKOPEDIA']);
                    });
                }
            });

            return;
        }

        if ($channelFilter === 'shopee') {
            $query->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE);

            return;
        }

        if ($channelFilter === 'tiktok') {
            $query->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->where(function (Builder $inner) {
                    $inner->whereNull('commerce_platform')
                        ->orWhere('commerce_platform', '')
                        ->orWhereRaw('UPPER(commerce_platform) != ?', ['TOKOPEDIA']);
                });

            return;
        }

        if ($channelFilter === 'tokopedia') {
            $query->where('channel', MarketplaceWebhookRecorder::CHANNEL_TIKTOK)
                ->whereRaw('UPPER(COALESCE(commerce_platform, "")) = ?', ['TOKOPEDIA']);
        }
    }

    private function applyReturnSignalScope(Builder $query): void
    {
        $statuses = $this->eligibility->candidateStatuses();
        $maxDays = (int) config('marketplace.post_return.max_age_days', 30);
        $earliest = Carbon::now()->subDays($maxDays)->startOfDay();

        $query->where(function (Builder $inner) use ($statuses) {
            foreach ($statuses as $status) {
                $inner->orWhereRaw('UPPER(TRIM(order_status)) = ?', [strtoupper($status)]);
            }
            $inner->orWhere(function (Builder $cancelled) {
                $this->opsBucket->applyOpsBucketFilter($cancelled, MarketplaceChannelOrderOpsBucket::BUCKET_CANCELLED);
            });
        });

        $query->where(function (Builder $inner) use ($earliest) {
            $inner->where('marketplace_ordered_at', '>=', $earliest)
                ->orWhere('last_event_at', '>=', $earliest);
        });
    }

    private function applyWorkflowStatusFilter(Builder $query, string $status): void
    {
        $status = strtolower(trim($status));
        if ($status === '' || $status === self::STATUS_ALL) {
            return;
        }

        if ($status === self::STATUS_POSTED) {
            if (Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')) {
                $query->where('posted_return_transaction_id', '>', 0);
            }

            return;
        }

        if ($status === self::STATUS_VERIFIED) {
            if ($this->verifyColumnsReady()) {
                $query->whereNotNull('return_verified_at');
            }
            if ($this->dismissColumnsReady()) {
                $query->whereNull('return_dismissed_at');
            }
            if (Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')) {
                $query->where(function (Builder $inner) {
                    $inner->whereNull('posted_return_transaction_id')
                        ->orWhere('posted_return_transaction_id', '<=', 0);
                });
            }

            return;
        }

        if ($status === self::STATUS_PENDING_VERIFICATION) {
            if ($this->verifyColumnsReady()) {
                $query->whereNull('return_verified_at');
            }
            if ($this->dismissColumnsReady()) {
                $query->whereNull('return_dismissed_at');
            }
            if (Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')) {
                $query->where(function (Builder $inner) {
                    $inner->whereNull('posted_return_transaction_id')
                        ->orWhere('posted_return_transaction_id', '<=', 0);
                });
            }

            return;
        }

        if ($status === self::STATUS_DISMISSED) {
            if ($this->dismissColumnsReady()) {
                $query->whereNotNull('return_dismissed_at');
            }
            if (Schema::hasColumn('marketplace_channel_orders', 'posted_return_transaction_id')) {
                $query->where(function (Builder $inner) {
                    $inner->whereNull('posted_return_transaction_id')
                        ->orWhere('posted_return_transaction_id', '<=', 0);
                });
            }
        }
    }
}