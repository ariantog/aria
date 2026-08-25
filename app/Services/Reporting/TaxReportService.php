<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxReportService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2025;

    public function __construct(
        private readonly ReportingSummaryRecorder $recorder,
    ) {}

    /**
     * @return array{
     *     keluaran_dpp: float,
     *     keluaran_tax: float,
     *     masukan_dpp: float,
     *     masukan_tax: float,
     *     net_ppn: float,
     *     pph_final: float,
     *     tax_paid: float,
     *     raw_keluaran_dpp: float,
     *     raw_keluaran_tax: float,
     *     raw_masukan_dpp: float,
     *     raw_masukan_tax: float,
     *     retur_keluaran_dpp: float,
     *     retur_keluaran_tax: float,
     *     retur_masukan_dpp: float,
     *     retur_masukan_tax: float,
     * }
     */
    public function ringkasan(int $year, int $month, ?int $entityId): array
    {
        if ($year < self::MIN_YEAR) {
            return $this->emptyRingkasan();
        }

        $entityIds = $this->resolveEntityIds($entityId);
        if ($entityIds === []) {
            return $this->emptyRingkasan();
        }

        $row = ReportingMonthlyTaxSummary::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('reporting_entity_id', $entityIds)
            ->selectRaw('
                COALESCE(SUM(ppn_keluaran_dpp), 0) as raw_keluaran_dpp,
                COALESCE(SUM(ppn_keluaran_tax), 0) as raw_keluaran_tax,
                COALESCE(SUM(ppn_masukan_dpp), 0) as raw_masukan_dpp,
                COALESCE(SUM(ppn_masukan_tax), 0) as raw_masukan_tax,
                COALESCE(SUM(retur_keluaran_dpp), 0) as retur_keluaran_dpp,
                COALESCE(SUM(retur_keluaran_tax), 0) as retur_keluaran_tax,
                COALESCE(SUM(retur_masukan_dpp), 0) as retur_masukan_dpp,
                COALESCE(SUM(retur_masukan_tax), 0) as retur_masukan_tax,
                COALESCE(SUM(pph_final), 0) as pph_final,
                COALESCE(SUM(tax_paid), 0) as tax_paid
            ')
            ->first();

        $rawKeluaranDpp = (float) $row->raw_keluaran_dpp;
        $rawKeluaranTax = (float) $row->raw_keluaran_tax;
        $rawMasukanDpp = (float) $row->raw_masukan_dpp;
        $rawMasukanTax = (float) $row->raw_masukan_tax;
        $returKeluaranDpp = (float) $row->retur_keluaran_dpp;
        $returKeluaranTax = (float) $row->retur_keluaran_tax;
        $returMasukanDpp = (float) $row->retur_masukan_dpp;
        $returMasukanTax = (float) $row->retur_masukan_tax;

        $keluaranDpp = $rawKeluaranDpp - $returKeluaranDpp;
        $keluaranTax = $rawKeluaranTax - $returKeluaranTax;
        $masukanDpp = $rawMasukanDpp - $returMasukanDpp;
        $masukanTax = $rawMasukanTax - $returMasukanTax;

        return [
            'keluaran_dpp' => $keluaranDpp,
            'keluaran_tax' => $keluaranTax,
            'masukan_dpp' => $masukanDpp,
            'masukan_tax' => $masukanTax,
            'net_ppn' => $keluaranTax - $masukanTax,
            'pph_final' => (float) $row->pph_final,
            'tax_paid' => (float) $row->tax_paid,
            'raw_keluaran_dpp' => $rawKeluaranDpp,
            'raw_keluaran_tax' => $rawKeluaranTax,
            'raw_masukan_dpp' => $rawMasukanDpp,
            'raw_masukan_tax' => $rawMasukanTax,
            'retur_keluaran_dpp' => $returKeluaranDpp,
            'retur_keluaran_tax' => $returKeluaranTax,
            'retur_masukan_dpp' => $returMasukanDpp,
            'retur_masukan_tax' => $returMasukanTax,
        ];
    }

  /**
     * @return Collection<int, array{
     *     id: int,
     *     date: string,
     *     invoice: string|null,
     *     type: string,
     *     type_label: string,
     *     party: string,
     *     entity_name: string|null,
     *     dpp: float,
     *     ppn: float,
     * }>
     */
    public function keluaranRows(int $year, int $month, ?int $entityId): Collection
    {
        if ($year < self::MIN_YEAR) {
            return collect();
        }

        $entityIds = $this->resolveEntityIds($entityId);
        if ($entityIds === []) {
            return collect();
        }

        [$startDate, $endDate] = $this->monthRange($year, $month);
        $rows = collect();

        $sells = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', Transaction::TYPE_SELL)
            ->where('ppn', '>', 0)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($sells as $transaction) {
            $entity = $this->recorder->resolveEntityForSellTax($transaction);
            if (! $entity || ! $this->entityMatches($entity->id, $entityIds)) {
                continue;
            }

            $rows->push($this->formatRow(
                $transaction,
                'sell',
                'Penjualan',
                $this->partyName($transaction->receiver_id),
                $entity->name,
                abs((float) $transaction->total),
                abs((float) $transaction->ppn),
            ));
        }

        $cashIns = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('receiver_type', Addrbook::TYPE_BANK)
            ->where('sender_type', '!=', Addrbook::TYPE_ACCOUNT)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($cashIns as $transaction) {
            if (! $this->recorder->cashInShouldInferKeluaranTax($transaction)) {
                continue;
            }

            $entity = ReportingEntity::findActiveForBank((int) $transaction->receiver_id);
            if (! $entity || ! $this->entityMatches($entity->id, $entityIds)) {
                continue;
            }

            $gross = abs((float) $transaction->total);
            $rate = $this->getPpnRate();
            $dpp = round($gross / (1 + $rate), 2);
            $tax = round($gross - $dpp, 2);

            $rows->push([
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'invoice' => $transaction->invoice,
                'type' => 'cash_in',
                'type_label' => 'Penerimaan (non-pelanggan)',
                'party' => $this->partyName($transaction->sender_id),
                'entity_name' => $entity->name,
                'dpp' => $dpp,
                'ppn' => $tax,
            ]);
        }

        $returns = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', Transaction::TYPE_RETURN)
            ->where('ppn', '>', 0)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($returns as $transaction) {
            $entity = $this->recorder->resolveEntityForSellTax($transaction);
            if (! $entity || ! $this->entityMatches($entity->id, $entityIds)) {
                continue;
            }

            $rows->push($this->formatRow(
                $transaction,
                'return',
                'Retur Keluaran',
                $this->partyName($transaction->sender_id),
                $entity->name,
                -abs((float) $transaction->total),
                -abs((float) $transaction->ppn),
            ));
        }

        return $rows->sortBy([
            ['date', 'asc'],
            ['id', 'asc'],
        ])->values();
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     date: string,
     *     invoice: string|null,
     *     type: string,
     *     type_label: string,
     *     party: string,
     *     entity_name: string|null,
     *     dpp: float,
     *     ppn: float,
     * }>
     */
    public function masukanRows(int $year, int $month, ?int $entityId): Collection
    {
        if ($year < self::MIN_YEAR) {
            return collect();
        }

        $entityIds = $this->resolveEntityIds($entityId);
        if ($entityIds === []) {
            return collect();
        }

        [$startDate, $endDate] = $this->monthRange($year, $month);
        $rows = collect();

        $buys = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', Transaction::TYPE_BUY)
            ->where('ppn', '>', 0)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($buys as $transaction) {
            $entity = $this->recorder->resolveEntityForBuyTax($transaction);
            if (! $entity || ! $this->entityMatches($entity->id, $entityIds)) {
                continue;
            }

            $rows->push($this->formatRow(
                $transaction,
                'buy',
                'Pembelian',
                $this->partyName($transaction->sender_id),
                $entity->name,
                abs((float) $transaction->total),
                abs((float) $transaction->ppn),
            ));
        }

        $returnSuppliers = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', Transaction::TYPE_RETURN_SUPPLIER)
            ->where('ppn', '>', 0)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($returnSuppliers as $transaction) {
            $entity = $this->recorder->resolveEntityForBuyTax($transaction);
            if (! $entity || ! $this->entityMatches($entity->id, $entityIds)) {
                continue;
            }

            $rows->push($this->formatRow(
                $transaction,
                'return_supplier',
                'Retur Masukan',
                $this->partyName($transaction->receiver_id),
                $entity->name,
                -abs((float) $transaction->total),
                -abs((float) $transaction->ppn),
            ));
        }

        return $rows->sortBy([
            ['date', 'asc'],
            ['id', 'asc'],
        ])->values();
    }

    public function exportCsv(
        int $year,
        int $month,
        ?int $entityId,
        string $entityLabel,
    ): StreamedResponse {
        $ringkasan = $this->ringkasan($year, $month, $entityId);
        $keluaran = $this->keluaranRows($year, $month, $entityId);
        $masukan = $this->masukanRows($year, $month, $entityId);

        $filename = sprintf(
            'ppn-report-%s-%04d-%02d.csv',
            str($entityLabel)->slug(),
            $year,
            $month,
        );

        return new StreamedResponse(function () use ($year, $month, $entityLabel, $ringkasan, $keluaran, $masukan) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Laporan PPN', $entityLabel, sprintf('%04d-%02d', $year, $month)]);
            fputcsv($out, []);
            fputcsv($out, ['Ringkasan']);
            fputcsv($out, ['Keluaran DPP', $ringkasan['keluaran_dpp']]);
            fputcsv($out, ['Keluaran PPN', $ringkasan['keluaran_tax']]);
            fputcsv($out, ['Masukan DPP', $ringkasan['masukan_dpp']]);
            fputcsv($out, ['Masukan PPN', $ringkasan['masukan_tax']]);
            fputcsv($out, ['Net PPN', $ringkasan['net_ppn']]);
            fputcsv($out, ['PPh Final', $ringkasan['pph_final']]);
            fputcsv($out, ['Tax Paid', $ringkasan['tax_paid']]);
            fputcsv($out, []);
            fputcsv($out, ['Keluaran']);
            fputcsv($out, ['Tanggal', 'Invoice', 'Tipe', 'Pihak', 'Entitas', 'DPP', 'PPN', 'ID Transaksi']);
            foreach ($keluaran as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['invoice'],
                    $row['type_label'],
                    $row['party'],
                    $row['entity_name'],
                    $row['dpp'],
                    $row['ppn'],
                    $row['id'],
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Masukan']);
            fputcsv($out, ['Tanggal', 'Invoice', 'Tipe', 'Pihak', 'Entitas', 'DPP', 'PPN', 'ID Transaksi']);
            foreach ($masukan as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['invoice'],
                    $row['type_label'],
                    $row['party'],
                    $row['entity_name'],
                    $row['dpp'],
                    $row['ppn'],
                    $row['id'],
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function yearOptions(): array
    {
        $currentYear = (int) now()->year;
        $start = max(self::MIN_YEAR, $currentYear);

        return range($start, self::MIN_YEAR);
    }

    public function entityLabel(?int $entityId): string
    {
        if ($entityId === null || $entityId === self::CONSOLIDATED_ENTITY) {
            return 'Konsolidasi';
        }

        return ReportingEntity::query()->find($entityId)?->name ?? 'Entitas';
    }

    /**
     * @return list<int>
     */
    private function resolveEntityIds(?int $entityId): array
    {
        if ($entityId === null || $entityId === self::CONSOLIDATED_ENTITY) {
            return ReportingEntity::query()
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        $exists = ReportingEntity::query()->whereKey($entityId)->exists();

        return $exists ? [$entityId] : [];
    }

    private function entityMatches(int $entityId, array $entityIds): bool
    {
        return in_array($entityId, $entityIds, true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    private function getPpnRate(): float
    {
        return (float) Setting::getValue('ppn_rate', 11) / 100;
    }

    private function partyName(int $addrbookId): string
    {
        return Addrbook::withTrashed()->find($addrbookId)?->name ?? '—';
    }

    /**
     * @return array{
     *     id: int,
     *     date: string,
     *     invoice: string|null,
     *     type: string,
     *     type_label: string,
     *     party: string,
     *     entity_name: string|null,
     *     dpp: float,
     *     ppn: float,
     * }
     */
    private function formatRow(
        Transaction $transaction,
        string $type,
        string $typeLabel,
        string $party,
        ?string $entityName,
        float $dpp,
        float $ppn,
    ): array {
        return [
            'id' => $transaction->id,
            'date' => $transaction->date->toDateString(),
            'invoice' => $transaction->invoice,
            'type' => $type,
            'type_label' => $typeLabel,
            'party' => $party,
            'entity_name' => $entityName,
            'dpp' => $dpp,
            'ppn' => $ppn,
        ];
    }

    /**
     * @return array{
     *     keluaran_dpp: float,
     *     keluaran_tax: float,
     *     masukan_dpp: float,
     *     masukan_tax: float,
     *     net_ppn: float,
     *     pph_final: float,
     *     tax_paid: float,
     *     raw_keluaran_dpp: float,
     *     raw_keluaran_tax: float,
     *     raw_masukan_dpp: float,
     *     raw_masukan_tax: float,
     *     retur_keluaran_dpp: float,
     *     retur_keluaran_tax: float,
     *     retur_masukan_dpp: float,
     *     retur_masukan_tax: float,
     * }
     */
    private function emptyRingkasan(): array
    {
        return [
            'keluaran_dpp' => 0.0,
            'keluaran_tax' => 0.0,
            'masukan_dpp' => 0.0,
            'masukan_tax' => 0.0,
            'net_ppn' => 0.0,
            'pph_final' => 0.0,
            'tax_paid' => 0.0,
            'raw_keluaran_dpp' => 0.0,
            'raw_keluaran_tax' => 0.0,
            'raw_masukan_dpp' => 0.0,
            'raw_masukan_tax' => 0.0,
            'retur_keluaran_dpp' => 0.0,
            'retur_keluaran_tax' => 0.0,
            'retur_masukan_dpp' => 0.0,
            'retur_masukan_tax' => 0.0,
        ];
    }
}
