<?php

namespace App\Services\Lark;

use App\Models\Jubelioorder;
use App\Models\OperationalAlert;
use App\Services\Jubelio\JubelioOrderSyncStatus;

class JubelioErrorBacklogScanService
{
    public const FINGERPRINT = 'jubelio:error:backlog';

    public function __construct(
        protected OperationalAlertDispatcher $alerts,
        protected LarkAlertLinkBuilder $links,
    ) {}

    public function run(): int
    {
        if (! config('lark.enabled')) {
            return 0;
        }

        $settings = app(LarkSettingsService::class);
        if (! $settings->eventSendEnabled('jubelio.error.backlog')) {
            return 0;
        }

        $threshold = (int) $settings->jubelio()['error_backlog_threshold'];
        $errorCount = $this->attentionErrorCount();

        if ($errorCount < $threshold) {
            $this->rearmWhenBelowThreshold();

            return 0;
        }

        $sampleInvoices = Jubelioorder::query()
            ->where('status', 1)
            ->whereIn('error_type', JubelioOrderSyncStatus::ATTENTION_ERROR_TYPES)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->pluck('invoice')
            ->map(fn ($inv) => (string) $inv)
            ->all();

        $this->alerts->dispatch(
            'jubelio.error.backlog',
            self::FINGERPRINT,
            'warning',
            'Backlog error sync Jubelio',
            [
                'Jumlah error' => (string) $errorCount,
                'Ambang' => (string) $threshold,
                'Contoh invoice' => $sampleInvoices !== [] ? implode(', ', $sampleInvoices) : '—',
                '_actions' => [
                    app(LarkCardBuilder::class)->urlButton(
                        'Buka daftar error di Aria',
                        $this->links->jubelioErrorBacklogIndexUrl(),
                    ),
                ],
            ],
        );

        return 1;
    }

    public function attentionErrorCount(): int
    {
        return (int) Jubelioorder::query()
            ->where('status', 1)
            ->whereIn('error_type', JubelioOrderSyncStatus::ATTENTION_ERROR_TYPES)
            ->count();
    }

    private function rearmWhenBelowThreshold(): void
    {
        OperationalAlert::query()
            ->where('fingerprint', self::FINGERPRINT)
            ->where('status', 'open')
            ->update([
                'status' => 'rearmed',
                'rearmed_at' => now(),
            ]);
    }
}
