<?php

namespace App\Services\Jubelio;

use App\Services\JubelioService;
use Illuminate\Support\Facades\Log;

/**
 * Resolve Jubelio salesorder_no from courier AWB / resi via targeted API queries (no blind list scan).
 */
class JubelioTrackingLookup
{
    public function __construct(
        private JubelioService $jubelioService,
    ) {}

    /**
     * @return array{
     *   status: 'found',
     *   salesorder_no: string,
     *   salesorder_id: string|int|null,
     * }|array{
     *   status: 'not_found'|'token_missing'|'auth_denied'|'api_error',
     *   message: string,
     * }
     */
    public function resolve(string $rawTracking, ?int $storeId = null): array
    {
        $this->jubelioService->resetOrdersQueryProbe();

        $variants = $this->trackingVariants($rawTracking);
        if ($variants === []) {
            return [
                'status' => 'not_found',
                'message' => 'Resi tidak ditemukan di Jubelio.',
            ];
        }

        foreach ($variants as $needle) {
            $found = $this->lookupNeedle($needle, $storeId);
            if ($found !== null) {
                return [
                    'status' => 'found',
                    'salesorder_no' => $found['salesorder_no'],
                    'salesorder_id' => $found['salesorder_id'],
                ];
            }
        }

        if ($storeId !== null && $storeId > 0) {
            foreach ($variants as $needle) {
                $found = $this->lookupNeedle($needle, null);
                if ($found !== null) {
                    Log::info('Jubelio resi lookup matched after dropping store filter.', [
                        'tracking' => $needle,
                        'store_id' => $storeId,
                        'invoice' => $found['salesorder_no'],
                    ]);

                    return [
                        'status' => 'found',
                        'salesorder_no' => $found['salesorder_no'],
                        'salesorder_id' => $found['salesorder_id'],
                    ];
                }
            }
        }

        return $this->jubelioService->ordersQueryFailureResult();
    }

    /**
     * @return array{salesorder_no: string, salesorder_id: string|int|null}|null
     */
    private function lookupNeedle(string $needle, ?int $storeId): ?array
    {
        foreach (['tracking_no', 'trackingNo', 'airwaybill_no', 'awb', 'resi'] as $param) {
            $response = $this->jubelioService->fetchSalesOrdersQuery(1, 50, [$param => $needle]);
            $match = $this->matchFromListResponse($response, $needle, $storeId, true);
            if ($match !== null) {
                return $match;
            }
        }

        $days = (int) config('jubelio_resi_scan.tracking_search_days', 21);
        $from = now()->utc()->subDays(max(1, $days))->format('Y-m-d\TH:i:s\Z');
        $to = now()->utc()->format('Y-m-d\TH:i:s\Z');

        foreach (['q', 'search', 'keyword'] as $param) {
            $response = $this->jubelioService->fetchSalesOrdersQuery(1, 50, [
                $param => $needle,
                'transactionDateFrom' => $from,
                'transactionDateTo' => $to,
            ]);
            $match = $this->matchFromListResponse($response, $needle, $storeId, false);
            if ($match !== null) {
                return $match;
            }
        }

        $ready = $this->jubelioService->fetchReadyToPickOrders(1, 50, ['q' => $needle]);

        return $this->matchFromListResponse($ready, $needle, $storeId, false);
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array{salesorder_no: string, salesorder_id: string|int|null}|null
     */
    private function matchFromListResponse(?array $response, string $needle, ?int $storeId, bool $trustListTracking): ?array
    {
        if ($response === null) {
            return null;
        }

        $rows = is_array($response['data'] ?? null) ? $response['data'] : [];
        if ($rows === []) {
            return null;
        }

        if ($trustListTracking) {
            $match = $this->pickRowWithTrackingOnList($rows, $needle, $storeId);
            if ($match !== null) {
                return $match;
            }
        }

        return $this->pickRowByOrderDetail($rows, $needle, $storeId);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{salesorder_no: string, salesorder_id: string|int|null}|null
     */
    private function pickRowWithTrackingOnList(array $rows, string $needle, ?int $storeId): ?array
    {
        foreach ($rows as $row) {
            if (! is_array($row) || ! $this->rowPassesStore($row, $storeId)) {
                continue;
            }

            if (! $this->jubelioService->trackingValueMatchesRow($row, $needle)) {
                continue;
            }

            $invoice = trim((string) ($row['salesorder_no'] ?? ''));
            if ($invoice === '') {
                continue;
            }

            return [
                'salesorder_no' => $invoice,
                'salesorder_id' => $row['salesorder_id'] ?? null,
            ];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{salesorder_no: string, salesorder_id: string|int|null}|null
     */
    private function pickRowByOrderDetail(array $rows, string $needle, ?int $storeId, int $maxFetches = 8): ?array
    {
        $checked = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || ! $this->rowPassesStore($row, $storeId)) {
                continue;
            }

            $orderId = $row['salesorder_id'] ?? null;
            if ($orderId === null || $orderId === '') {
                continue;
            }

            if ($checked >= $maxFetches) {
                break;
            }
            $checked++;

            $detail = $this->jubelioService->fetchSalesOrder($orderId);
            if (! is_array($detail)) {
                continue;
            }

            if (! $this->jubelioService->trackingValueMatchesPayload($detail, $needle)) {
                continue;
            }

            $invoice = trim((string) ($detail['salesorder_no'] ?? $row['salesorder_no'] ?? ''));
            if ($invoice === '') {
                continue;
            }

            return [
                'salesorder_no' => $invoice,
                'salesorder_id' => $detail['salesorder_id'] ?? $orderId,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowPassesStore(array $row, ?int $storeId): bool
    {
        if ($storeId === null || $storeId <= 0) {
            return true;
        }

        $rowStore = (int) ($row['store_id'] ?? $row['jubelio_store_id'] ?? 0);

        return $rowStore <= 0 || $rowStore === $storeId;
    }

    /**
     * @return list<string>
     */
    private function trackingVariants(string $raw): array
    {
        $primary = $this->jubelioService->normalizeTrackingNumber($raw);
        $out = [];
        if ($primary !== '') {
            $out[] = $primary;
        }

        $alnum = preg_replace('/[^A-Za-z0-9]/', '', trim($raw)) ?? '';
        $upper = strtoupper($alnum);
        if ($upper !== '' && ! in_array($upper, $out, true)) {
            $out[] = $upper;
        }

        if (preg_match('/\((\d+)\)(\d+)/', $raw, $m) === 1) {
            $combined = strtoupper($m[1].$m[2]);
            if (! in_array($combined, $out, true)) {
                $out[] = $combined;
            }
        }

        return $out;
    }
}
