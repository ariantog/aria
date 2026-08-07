<?php

namespace App\Services;

use App\Models\Crongetorder;
use App\Models\Crongetorderdetail;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class JubelioGetOrdersService
{
    /** @var list<string> */
    private const ELIGIBLE_STATUSES = ['SHIPPED', 'COMPLETED', 'RETURNED'];

    public function __construct(
        private JubelioService $jubelioService,
    ) {}

    /**
     * Fetch up to $maxPages API pages, then reconcile when the range is fully loaded.
     *
     * @return array{fetched_pages: int, completed: bool, remaining: int|null}
     */
    public function processBatch(Crongetorder $import, int $maxPages = 10, int $maxSeconds = 50): array
    {
        $import->refresh();
        $deadline = microtime(true) + $maxSeconds;
        $fetchedPages = 0;

        while ($fetchedPages < $maxPages && microtime(true) < $deadline) {
            if ($import->total > 0 && $import->count >= $import->total) {
                break;
            }

            $hasMore = $this->fetchNextPage($import);
            $fetchedPages++;
            $import->refresh();

            if (! $hasMore) {
                break;
            }
        }

        $completed = false;
        if ($import->total > 0 && $import->count >= $import->total && $import->isRunning()) {
            $this->reconcile($import);
            $import->update(['status' => 1, 'step' => 3]);
            $completed = true;
        }

        return [
            'fetched_pages' => $fetchedPages,
            'completed' => $completed,
            'remaining' => $import->total > 0 ? max(0, $import->total - $import->count) : null,
        ];
    }

    /**
     * Run the full import synchronously (all pages + reconcile).
     */
    public function processSync(Crongetorder $import, int $maxPagesPerBatch = 50): void
    {
        do {
            $result = $this->processBatch($import, $maxPagesPerBatch, 300);
            $import->refresh();
        } while (! $result['completed'] && $import->isRunning());
    }

    public function fetchNextPage(Crongetorder $import): bool
    {
        $range = $import->dateRangeIso();
        $page = $import->count + 1;

        $response = $this->jubelioService->fetchSalesOrders($page, 200, $range['from'], $range['to']);
        if (! $response) {
            throw new \RuntimeException('Gagal mengambil data order dari API Jubelio.');
        }

        $totalCount = (int) ($response['totalCount'] ?? 0);

        if ($totalCount === 0) {
            $import->update(['total' => 0, 'count' => 0, 'status' => 1, 'step' => 3]);

            return false;
        }

        if ($import->total < 1) {
            $import->update(['total' => (int) ceil($totalCount / 200)]);
        }

        $rows = $response['data'] ?? [];
        if ($rows !== []) {
            $this->insertListRows($import, $rows);
            $import->increment('count');
        }

        return $import->fresh()->count < $import->total;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertListRows(Crongetorder $import, array $rows): int
    {
        $now = now();
        $batch = [];

        foreach ($rows as $row) {
            if (! $this->isEligibleListRow($row)) {
                continue;
            }

            $batch[] = [
                'crongetorder_id' => $import->id,
                'jubelio_order_id' => $row['salesorder_id'] ?? null,
                'invoice' => $row['salesorder_no'] ?? null,
                'location_name' => $row['location_name'] ?? null,
                'store_name' => $row['store_name'] ?? null,
                'order_status' => $row['internal_status'] ?? null,
                'is_canceled' => $row['is_canceled'] ?? null,
                'payload' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('crongetorderdetails')->insert($chunk);
        }

        return count($batch);
    }

    /**
     * Drop ineligible rows and orders already present in Aria (single reconcile pass).
     */
    public function reconcile(Crongetorder $import): int
    {
        $removed = Crongetorderdetail::where('crongetorder_id', $import->id)
            ->where(function ($query) {
                $query->whereNotIn('order_status', self::ELIGIBLE_STATUSES)
                    ->orWhere('is_canceled', 'Y');
            })
            ->delete();

        $removed += $this->removeInvoicesAlreadyInAria($import->id);

        return $removed;
    }

    public function removeInvoicesAlreadyInAria(int $importId): int
    {
        $removed = 0;

        Crongetorderdetail::where('crongetorder_id', $importId)
            ->whereNotNull('invoice')
            ->select('invoice')
            ->orderBy('invoice')
            ->chunk(1000, function ($details) use ($importId, &$removed) {
                $invoices = $details->pluck('invoice')->filter()->all();
                if ($invoices === []) {
                    return;
                }

                $existing = $this->findInvoicesAlreadyInAria($invoices);
                if ($existing === []) {
                    return;
                }

                $removed += Crongetorderdetail::where('crongetorder_id', $importId)
                    ->whereIn('invoice', $existing)
                    ->delete();
            });

        return $removed;
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
