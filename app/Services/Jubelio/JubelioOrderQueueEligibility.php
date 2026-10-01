<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class JubelioOrderQueueEligibility
{
    /** @var list<string> */
    private const ELIGIBLE_SELL_STATUSES = ['SHIPPED', 'RETURNED'];

    /** @var list<string> */
    private const TERMINAL_SELL_STATUSES = ['COMPLETED', 'CANCELED', 'CANCELLED'];

    /**
     * @param  array<string, mixed>  $row
     */
    public function isEligibleListRow(array $row): bool
    {
        if ($this->hasTerminalSellStatus($row)) {
            return false;
        }

        $status = $this->primarySellStatus($row);
        if (! in_array($status, self::ELIGIBLE_SELL_STATUSES, true)) {
            return false;
        }

        if (($row['is_canceled'] ?? 'N') === 'Y') {
            return false;
        }

        return ! $this->isBeforeEarliestAllowedDate($row);
    }

    /**
     * @param  array<string, mixed>  $apiData
     */
    public function isEligibleApiOrder(array $apiData): bool
    {
        return $this->isEligibleListRow($this->normalizeApiRow($apiData));
    }

    /**
     * Skip before API fetch when the queue row already shows a terminal Jubelio status.
     */
    public function rejectReasonForStoredOrder(Jubelioorder $order): ?string
    {
        if ($order->type !== 'SELL') {
            return null;
        }

        if ($this->hasTerminalSellStatus(['order_status' => $order->order_status])) {
            return $this->terminalStatusMessage($order->order_status);
        }

        return null;
    }

    /**
     * Block cron / manual posting when the order is outside the catch-up window or wrong status.
     *
     * @param  array<string, mixed>  $payload
     */
    public function rejectReasonForProcessing(Jubelioorder $order, array $payload): ?string
    {
        if ($order->type === 'SELL') {
            $row = $this->normalizeApiRow($payload, $order);

            if ($this->hasTerminalSellStatus($row)) {
                return $this->terminalStatusMessage($this->primarySellStatus($row));
            }

            if (! $this->isEligibleListRow($row)) {
                $status = $this->primarySellStatus($row);
                if (! in_array($status, self::ELIGIBLE_SELL_STATUSES, true)) {
                    return 'Order tidak diproses: status Jubelio harus SHIPPED (bukan COMPLETED / status lain).';
                }
                if (($row['is_canceled'] ?? 'N') === 'Y') {
                    return 'Order dibatalkan di Jubelio — tidak diproses.';
                }

                return 'Order tidak diproses: tanggal transaksi di luar '.$this->maxAgeDays().' hari terakhir (hanya catch-up webhook terbaru).';
            }

            return null;
        }

        if ($order->type === 'RETURN') {
            if ($this->isBeforeEarliestAllowedDate($this->normalizeApiRow($payload))) {
                return 'Retur tidak diproses: tanggal transaksi di luar '.$this->maxAgeDays().' hari terakhir.';
            }
        }

        return null;
    }

    public function maxAgeDays(): int
    {
        return (int) config('services.jubelio.order_queue_max_age_days', 30);
    }

    public function earliestAllowedDate(): ?CarbonInterface
    {
        $days = $this->maxAgeDays();
        if ($days <= 0) {
            return null;
        }

        return Carbon::parse(now()->subDays($days)->startOfDay());
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isBeforeEarliestAllowedDate(array $row): bool
    {
        $earliest = $this->earliestAllowedDate();
        if ($earliest === null) {
            return false;
        }

        $date = $row['transaction_date'] ?? $row['created_date'] ?? null;
        if ($date === null || $date === '') {
            return false;
        }

        return Carbon::parse($date)->lt($earliest);
    }

    /**
     * @param  array<string, mixed>  $apiData
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $apiData
     * @return array<string, mixed>
     */
    public function normalizeApiRow(array $apiData, ?Jubelioorder $order = null): array
    {
        return array_merge($apiData, [
            'order_status' => $order?->order_status,
            'is_canceled' => $apiData['is_canceled'] ?? 'N',
            'transaction_date' => $apiData['transaction_date'] ?? null,
            'created_date' => $apiData['created_date'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function hasTerminalSellStatus(array $row): bool
    {
        foreach ($this->sellStatusValues($row) as $status) {
            if (in_array($status, self::TERMINAL_SELL_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function primarySellStatus(array $row): string
    {
        foreach ($this->sellStatusValues($row) as $status) {
            return $status;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected function sellStatusValues(array $row): array
    {
        $values = [];
        foreach (['internal_status', 'channel_status', 'wms_status', 'status', 'order_status'] as $key) {
            $raw = $row[$key] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $values[] = strtoupper(trim((string) $raw));
        }

        return array_values(array_unique($values));
    }

    protected function terminalStatusMessage(?string $status): string
    {
        $label = strtoupper(trim((string) ($status ?? 'COMPLETED')));

        return 'Order tidak diproses: status Jubelio '.$label.' (hanya SHIPPED yang diposting ke Aria).';
    }
}
