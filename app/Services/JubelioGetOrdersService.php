<?php

namespace App\Services;

use App\Models\Crongetorder;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class JubelioGetOrdersService
{
    /** @var list<string> */
    private const ELIGIBLE_STATUSES = ['SHIPPED', 'COMPLETED', 'RETURNED'];

    private const PAGE_SIZE = 200;

    public function __construct(
        private JubelioService $jubelioService,
    ) {}

    /**
     * Poll recent days for missing orders (used by scheduled task).
     */
    public function pollRecentDays(?int $days = null): int
    {
        $days = $days ?? (int) config('services.jubelio.poll_days', 7);
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        return $this->fetchAndQueueMissing($from, $to);
    }

    /**
     * Run a tracked manual import (all pages in one pass).
     */
    public function runImport(Crongetorder $import): int
    {
        $import->refresh();
        $range = $import->dateRangeCarbon();

        return $this->fetchAndQueueMissing($range['from'], $range['to'], $import);
    }

    /**
     * Fetch one cron batch (legacy entry point for incremental runs).
     *
     * @return array{fetched_pages: int, completed: bool, remaining: int|null, orders_queued: int}
     */
    public function processBatch(Crongetorder $import, int $maxPages = 10, int $maxSeconds = 50): array
    {
        $import->refresh();
        $deadline = microtime(true) + $maxSeconds;
        $fetchedPages = 0;
        $ordersQueued = 0;

        while ($fetchedPages < $maxPages && microtime(true) < $deadline) {
            if ($import->total > 0 && $import->count >= $import->total) {
                break;
            }

            $result = $this->fetchNextPage($import);
            $fetchedPages++;
            $ordersQueued += $result['queued'];
            $import->refresh();

            if (! $result['has_more']) {
                break;
            }
        }

        $completed = false;
        if ($import->total > 0 && $import->count >= $import->total && $import->isRunning()) {
            $import->update(['status' => 1, 'step' => 3]);
            $completed = true;
        }

        return [
            'fetched_pages' => $fetchedPages,
            'completed' => $completed,
            'remaining' => $import->total > 0 ? max(0, $import->total - $import->count) : null,
            'orders_queued' => $ordersQueued,
        ];
    }

    /**
     * Run the full import synchronously (all pages).
     */
    public function processSync(Crongetorder $import): int
    {
        return $this->runImport($import);
    }

    /**
     * @return array{has_more: bool, queued: int}
     */
    public function fetchNextPage(Crongetorder $import): array
    {
        $range = $import->dateRangeIso();
        $page = $import->count + 1;

        $response = $this->jubelioService->fetchSalesOrders($page, self::PAGE_SIZE, $range['from'], $range['to']);
        if (! $response) {
            throw new \RuntimeException('Gagal mengambil data order dari API Jubelio.');
        }

        $totalCount = (int) ($response['totalCount'] ?? 0);

        if ($totalCount === 0) {
            $import->update(['total' => 0, 'count' => 0, 'status' => 1, 'step' => 3]);

            return ['has_more' => false, 'queued' => 0];
        }

        if ($import->total < 1) {
            $import->update(['total' => (int) ceil($totalCount / self::PAGE_SIZE)]);
        }

        $rows = $response['data'] ?? [];
        $queued = 0;
        if ($rows !== []) {
            $queued = $this->queueEligibleRows($rows);
            $import->increment('count');
            if ($queued > 0) {
                $import->increment('orders_queued', $queued);
            }
        }

        $import->refresh();

        return [
            'has_more' => $import->count < $import->total,
            'queued' => $queued,
        ];
    }

    /**
     * Fetch all pages and queue missing orders in a single pass.
     */
    public function fetchAndQueueMissing(CarbonInterface $from, CarbonInterface $to, ?Crongetorder $import = null): int
    {
        $dateFrom = $from->utc()->format('Y-m-d\TH:i:s\Z');
        $dateTo = $to->utc()->format('Y-m-d\TH:i:s\Z');
        $page = 1;
        $totalPages = null;
        $totalQueued = 0;

        while (true) {
            $response = $this->jubelioService->fetchSalesOrders($page, self::PAGE_SIZE, $dateFrom, $dateTo);
            if (! $response) {
                throw new \RuntimeException('Gagal mengambil data order dari API Jubelio.');
            }

            $totalCount = (int) ($response['totalCount'] ?? 0);
            if ($totalCount === 0) {
                $import?->update(['total' => 0, 'count' => 0, 'status' => 1, 'step' => 3]);

                return $totalQueued;
            }

            if ($totalPages === null) {
                $totalPages = (int) ceil($totalCount / self::PAGE_SIZE);
                $import?->update(['total' => $totalPages]);
            }

            $rows = $response['data'] ?? [];
            if ($rows !== []) {
                $queued = $this->queueEligibleRows($rows);
                $totalQueued += $queued;

                if ($import) {
                    $import->increment('count');
                    if ($queued > 0) {
                        $import->increment('orders_queued', $queued);
                    }
                }
            }

            if ($page >= $totalPages) {
                break;
            }

            $page++;
        }

        $import?->update(['status' => 1, 'step' => 3]);

        return $totalQueued;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function queueEligibleRows(array $rows): int
    {
        $eligible = [];
        foreach ($rows as $row) {
            if (! $this->isEligibleListRow($row)) {
                continue;
            }

            $invoice = $row['salesorder_no'] ?? null;
            if (! $invoice) {
                continue;
            }

            $eligible[] = $row;
        }

        if ($eligible === []) {
            return 0;
        }

        $invoices = array_values(array_unique(array_map(
            fn (array $row) => $row['salesorder_no'],
            $eligible,
        )));

        $existing = $this->existingInvoiceLookup($invoices);
        $now = now();
        $batch = [];

        foreach ($eligible as $row) {
            $invoice = $row['salesorder_no'];
            if (isset($existing[$invoice])) {
                continue;
            }

            $existing[$invoice] = true;

            $batch[] = [
                'jubelio_order_id' => $row['salesorder_id'] ?? null,
                'source' => 2,
                'invoice' => $invoice,
                'type' => 'SELL',
                'order_status' => $row['internal_status'] ?? 'SHIPPED',
                'run_count' => 0,
                'payload' => '{}',
                'status' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($batch === []) {
            return 0;
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('jubelioorders')->insert($chunk);
        }

        return count($batch);
    }

    /**
     * @param  list<string>  $invoices
     * @return list<string>
     */
    public function findInvoicesAlreadyInAria(array $invoices): array
    {
        if ($invoices === []) {
            return [];
        }

        $inTransactions = Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->whereIn('invoice_number', $invoices)
            ->pluck('invoice_number')
            ->all();

        $inJubelio = Jubelioorder::query()
            ->where('type', 'SELL')
            ->whereIn('invoice', $invoices)
            ->pluck('invoice')
            ->all();

        return array_values(array_unique(array_merge($inTransactions, $inJubelio)));
    }

    /**
     * @param  list<string>  $invoices
     * @return array<string, true>
     */
    public function existingInvoiceLookup(array $invoices): array
    {
        $lookup = [];
        foreach ($this->findInvoicesAlreadyInAria($invoices) as $invoice) {
            $lookup[$invoice] = true;
        }

        return $lookup;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lookupOrder(string $orderId): ?array
    {
        return $this->jubelioService->fetchSalesOrder($orderId);
    }

    /**
     * @param  array<string, mixed>  $apiData
     * @return array<string, mixed>
     */
    public function inspectApiOrder(array $apiData): array
    {
        $invoice = $apiData['salesorder_no'] ?? null;
        $orderId = $apiData['salesorder_id'] ?? null;
        $status = $apiData['internal_status'] ?? $apiData['status'] ?? '';

        $inTransaction = $invoice && Transaction::query()
            ->where('type', Transaction::TYPE_SELL)
            ->where('invoice_number', $invoice)
            ->exists();

        $existingOrder = null;
        if ($orderId) {
            $existingOrder = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('jubelio_order_id', $orderId)
                ->first();
        }
        if (! $existingOrder && $invoice) {
            $existingOrder = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('invoice', $invoice)
                ->first();
        }

        $inQueue = $existingOrder !== null;
        $eligible = $this->isEligibleApiOrder($apiData);

        return [
            'invoice' => $invoice,
            'order_id' => $orderId,
            'status' => $status,
            'store_name' => $apiData['source_name'] ?? $apiData['store_name'] ?? null,
            'location_name' => $apiData['location_name'] ?? null,
            'transaction_date' => $apiData['transaction_date'] ?? $apiData['created_date'] ?? null,
            'grand_total' => $apiData['grand_total'] ?? null,
            'is_canceled' => ($apiData['is_canceled'] ?? 'N') === 'Y',
            'eligible' => $eligible,
            'in_transaction' => $inTransaction,
            'in_queue' => $inQueue,
            'existing_order' => $existingOrder,
            'can_queue' => $eligible && $invoice && ! $inTransaction && ! $inQueue,
        ];
    }

    /**
     * @param  array<string, mixed>  $apiData
     * @return array{success: bool, message: string, order: ?Jubelioorder}
     */
    public function queueApiOrder(array $apiData): array
    {
        $inspection = $this->inspectApiOrder($apiData);

        if (! $inspection['eligible']) {
            return [
                'success' => false,
                'message' => 'Status order tidak memenuhi syarat (harus SHIPPED, COMPLETED, atau RETURNED dan tidak dibatalkan).',
                'order' => null,
            ];
        }

        if ($inspection['in_transaction']) {
            return [
                'success' => false,
                'message' => 'Invoice sudah ada di tabel transaksi — tidak dapat diantri.',
                'order' => null,
            ];
        }

        if ($inspection['in_queue']) {
            return [
                'success' => false,
                'message' => 'Order sudah ada di antrian Jubelio Orders.',
                'order' => $inspection['existing_order'],
            ];
        }

        $invoice = $inspection['invoice'];
        if (! $invoice) {
            return [
                'success' => false,
                'message' => 'Invoice tidak ditemukan pada data API.',
                'order' => null,
            ];
        }

        $order = Jubelioorder::create([
            'jubelio_order_id' => $inspection['order_id'],
            'source' => 2,
            'invoice' => $invoice,
            'type' => 'SELL',
            'order_status' => $inspection['status'] ?: 'SHIPPED',
            'run_count' => 0,
            'payload' => '{}',
            'status' => 0,
        ]);

        return [
            'success' => true,
            'message' => 'Order berhasil diantri. Cron jubelio:order-jubelio-to-aria akan memprosesnya.',
            'order' => $order,
        ];
    }

    /**
     * @param  array<string, mixed>  $apiData
     */
    public function isEligibleApiOrder(array $apiData): bool
    {
        return $this->isEligibleListRow([
            'internal_status' => $apiData['internal_status'] ?? $apiData['status'] ?? '',
            'is_canceled' => $apiData['is_canceled'] ?? 'N',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isEligibleListRow(array $row): bool
    {
        $status = $row['internal_status'] ?? '';
        if (! in_array($status, self::ELIGIBLE_STATUSES, true)) {
            return false;
        }

        return ($row['is_canceled'] ?? 'N') !== 'Y';
    }
}
