<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class JubelioOrderQueueEligibility
{
    /** @var list<string> */
    private const ELIGIBLE_SELL_STATUSES = ['SHIPPED', 'RETURNED'];

    /**
     * @param  array<string, mixed>  $row
     */
    public function isEligibleListRow(array $row): bool
    {
        $status = (string) ($row['internal_status'] ?? '');
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
     * Block cron / manual posting when the order is outside the catch-up window or wrong status.
     *
     * @param  array<string, mixed>  $payload
     */
    public function rejectReasonForProcessing(Jubelioorder $order, array $payload): ?string
    {
        if ($order->type === 'SELL') {
            if (! $this->isEligibleListRow($this->normalizeApiRow($payload))) {
                $status = (string) ($payload['internal_status'] ?? $payload['status'] ?? $order->order_status ?? '');
                if (! in_array($status, self::ELIGIBLE_SELL_STATUSES, true)) {
                    return 'Order tidak diproses: status Jubelio harus SHIPPED (bukan COMPLETED / status lain).';
                }
                if (($payload['is_canceled'] ?? 'N') === 'Y') {
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
    public function normalizeApiRow(array $apiData): array
    {
        return [
            'internal_status' => $apiData['internal_status'] ?? $apiData['status'] ?? '',
            'is_canceled' => $apiData['is_canceled'] ?? 'N',
            'transaction_date' => $apiData['transaction_date'] ?? null,
            'created_date' => $apiData['created_date'] ?? null,
        ];
    }
}
