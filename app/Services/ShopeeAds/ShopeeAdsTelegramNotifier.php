<?php

namespace App\Services\ShopeeAds;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram admin alerts — same idea as bots/bot.py _notify_admins (sendMessage curl).
 */
class ShopeeAdsTelegramNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token'))
            && $this->chatIds() !== [];
    }

    /**
     * @return list<string>
     */
    private function chatIds(): array
    {
        $raw = (string) config('services.telegram.chat_ids', '');

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function send(string $text): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $token = (string) config('services.telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        foreach ($this->chatIds() as $chatId) {
            try {
                $response = Http::timeout(10)->post($url, [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);

                if (! $response->successful()) {
                    Log::warning('Shopee Ads Telegram notify failed', [
                        'chat_id' => $chatId,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Shopee Ads Telegram notify error', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function notifyGmvIncrement(string $runTime, int $before, int $after): void
    {
        $this->send(sprintf(
            "⏱ *Iklan Produk GMV Max* increment @ %s WIB\n• GMV Max: %s → %s",
            $runTime,
            $this->fmtIdr($before),
            $this->fmtIdr($after),
        ));
    }

    /**
     * @param  list<string>  $lines
     */
    public function notifyItemIncrement(string $runTime, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $body = array_merge(
            ["⏱ *Iklan Produk (Individual)* pool @ {$runTime} WIB"],
            array_slice($lines, 0, 12),
        );

        if (count($lines) > 12) {
            $body[] = '…and '.(count($lines) - 12).' more';
        }

        $this->send(implode("\n", $body));
    }

    public function notifyDailyReset(int $gmvBudget, int $itemAdCount, int $itemBudgetPerAd): void
    {
        if ($itemAdCount === 0 && $gmvBudget <= 0) {
            $this->send('🔄 *Daily reset done* — no ads to reset.');

            return;
        }

        $lines = ['🔄 *Daily reset done*'];
        if ($gmvBudget > 0) {
            $lines[] = '• *Iklan Produk GMV Max*: 1 → '.$this->fmtIdr($gmvBudget);
        }
        if ($itemAdCount > 0) {
            $suffix = $itemAdCount > 1 ? ' each' : '';
            $lines[] = "• *Iklan Produk (Individual)*: {$itemAdCount} → ".$this->fmtIdr($itemBudgetPerAd).$suffix;
        }

        $this->send(implode("\n", $lines));
    }

    public function notifyReplenish(int $created, int $budgetPerAd, string $detail = ''): void
    {
        if ($created <= 0) {
            $this->send('🧩 *Individual product ads* — no new ads created. '.$detail);

            return;
        }

        $this->send(sprintf(
            "🧩 *Individual product ads*\n✅ Created %d new ad(s) at %s each.\n%s",
            $created,
            $this->fmtIdr($budgetPerAd),
            $detail,
        ));
    }

    public function notifyManualBoost(float $multiplier, bool $gmvApplied, int $itemsApplied): void
    {
        $this->send(sprintf(
            "🚀 *Manual budget boost* ×%s\n• GMV Max: %s\n• Individual ads: %d updated",
            $multiplier,
            $gmvApplied ? 'updated' : 'skipped',
            $itemsApplied,
        ));
    }

    private function fmtIdr(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
