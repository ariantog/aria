<?php

namespace App\Services\Reporting;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ReportingEntity;
use App\Models\WarehouseItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NeracaService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2025;

    public function __construct(
        private readonly InventoryRollForwardService $inventory,
        private readonly BalanceAsOfService $balances,
        private readonly YearTradeBalanceService $tradeBalances,
        private readonly ReportingContactFilter $contacts,
    ) {}

    /**
     * @return array{
     *     as_of: string,
     *     year: int,
     *     month: int,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     source: string,
     *     trade_source: string,
     *     year_start: string,
     *     year_end: string,
     *     persediaan: array<string, mixed>,
     *     aktiva_lancar: array<string, float>,
     *     aktiva_tetap: float,
     *     total_aktiva: float,
     *     kewajiban: array<string, float>,
     *     total_kewajiban: float,
     *     ekuitas: array<string, float>,
     *     total_ekuitas: float,
     *     total_pasiva: float,
     *     balance_check: float,
     *     drilldown: array<string, list<array{id: int, name: string, balance: float, entity_name: string|null}>>,
     * }
     */
    public function build(int $year, int $month, ?int $entityId, bool $refresh = false): array
    {
        $asOf = ReportingPeriod::asOf($year, $month);
        $isConsolidated = $entityId === null || $entityId === self::CONSOLIDATED_ENTITY;
        $resolvedEntityId = $isConsolidated ? self::CONSOLIDATED_ENTITY : (int) $entityId;

        $persediaan = $this->inventory->isBeforeStart($year, $month)
            ? $this->inventory->forMonth($year, $month)
            : $this->inventory->ensureThrough($year, $month)->last();

        $hadSnapshot = $this->balances->hasSnapshot($asOf->toDateString());
        [$yearStart, $yearEnd] = $this->tradeBalances->yearRange($year, $asOf);

        $rows = $this->balances->balancesAsOf($asOf, persist: true, refresh: $refresh)
            ->filter(fn (object $row) => $this->contacts->include($row))
            ->values();

        $scoped = $isConsolidated
            ? $rows
            : $rows->filter(fn (object $row) => (int) $row->reporting_entity_id === $resolvedEntityId);

        $kasRows = $scoped->where('customer_type', Addrbook::TYPE_BANK)->values();
        $piutangRows = $this->scopeTradeRows(
            $this->tradeBalances->receivables($yearStart, $yearEnd),
            $isConsolidated,
            $resolvedEntityId,
        );
        $hutangRows = $this->scopeTradeRows(
            $this->tradeBalances->payables($yearStart, $yearEnd),
            $isConsolidated,
            $resolvedEntityId,
        );

        $kas = (float) $kasRows->sum('balance');
        $piutang = (float) $piutangRows->sum(fn (object $row) => (float) $row->balance);
        $hutang = (float) $hutangRows->sum(fn (object $row) => (float) $row->balance);

        $persediaanClosing = $isConsolidated ? (float) ($persediaan['closing'] ?? 0) : 0.0;
        $aktivaTetap = $isConsolidated ? $this->aktivaTetap() : 0.0;

        $aktivaLancar = [
            'kas' => $kas,
            'piutang' => $piutang,
            'persediaan' => $persediaanClosing,
        ];
        $totalAktivaLancar = array_sum($aktivaLancar);
        $totalAktiva = $totalAktivaLancar + $aktivaTetap;

        $kewajiban = [
            'hutang_usaha' => $hutang,
        ];
        $totalKewajiban = array_sum($kewajiban);

        [$modal, $labaDitahanAwal] = $this->equity($resolvedEntityId, $isConsolidated);
        $labaDitahanPlug = $totalAktiva - $totalKewajiban - $modal - $labaDitahanAwal;

        $ekuitas = [
            'modal' => $modal,
            'laba_ditahan_awal' => $labaDitahanAwal,
            'laba_ditahan' => $labaDitahanPlug,
        ];
        $totalEkuitas = $modal + $labaDitahanAwal + $labaDitahanPlug;
        $totalPasiva = $totalKewajiban + $totalEkuitas;

        $entityNames = ReportingEntity::query()->pluck('name', 'id');

        return [
            'as_of' => $asOf->toDateString(),
            'year' => $year,
            'month' => $month,
            'entity_id' => $resolvedEntityId,
            'entity_label' => $this->entityLabel($resolvedEntityId),
            'is_consolidated' => $isConsolidated,
            'source' => ($hadSnapshot && ! $refresh) ? 'snapshot' : 'replay',
            'trade_source' => 'year_activity',
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
            'persediaan' => $persediaan,
            'aktiva_lancar' => $aktivaLancar,
            'aktiva_tetap' => $aktivaTetap,
            'total_aktiva_lancar' => $totalAktivaLancar,
            'total_aktiva' => $totalAktiva,
            'kewajiban' => $kewajiban,
            'total_kewajiban' => $totalKewajiban,
            'ekuitas' => $ekuitas,
            'total_ekuitas' => $totalEkuitas,
            'total_pasiva' => $totalPasiva,
            'balance_check' => $totalAktiva - $totalPasiva,
            'drilldown' => [
                'kas' => $this->formatDrilldown($kasRows, $entityNames),
                'piutang' => $this->formatDrilldown($piutangRows, $entityNames),
                'hutang' => $this->formatDrilldown($hutangRows, $entityNames),
            ],
        ];
    }

    public function exportXlsx(array $report): StreamedResponse
    {
        $mapKas = fn (array $row) => [
            $row['name'],
            $row['entity_name'],
            $row['balance'],
            'addrbook:'.$row['id'],
        ];
        $mapPiutang = fn (array $row) => [
            $row['name'],
            $row['entity_name'],
            $row['sell'] ?? 0,
            $row['cash_in'] ?? 0,
            $row['balance'],
            'addrbook:'.$row['id'],
        ];
        $mapHutang = fn (array $row) => [
            $row['name'],
            $row['entity_name'],
            $row['buy'] ?? $row['balance'],
            $row['balance'],
            'addrbook:'.$row['id'],
        ];

        return app(ReportingExcelExport::class)->download(
            sprintf(
                'neraca-%s-%s.xlsx',
                str($report['entity_label'])->slug(),
                $report['as_of'],
            ),
            'Neraca',
            [
                [
                    'title' => 'Neraca',
                    'rows' => [
                        ['Entitas', $report['entity_label']],
                        ['As of', $report['as_of']],
                        ['Sumber kas', $report['source']],
                        ['Piutang/hutang', 'aktivitas '.$report['year_start'].' – '.$report['year_end']],
                    ],
                ],
                [
                    'title' => 'Ringkasan',
                    'headers' => ['Akun', 'Jumlah'],
                    'rows' => [
                        ['Kas / Bank', $report['aktiva_lancar']['kas']],
                        ['Piutang usaha', $report['aktiva_lancar']['piutang']],
                        ['Persediaan', $report['aktiva_lancar']['persediaan']],
                        ['Aktiva lancar', $report['total_aktiva_lancar']],
                        ['Aktiva tetap', $report['aktiva_tetap']],
                        ['Total aktiva', $report['total_aktiva']],
                        ['Hutang usaha', $report['kewajiban']['hutang_usaha']],
                        ['Total kewajiban', $report['total_kewajiban']],
                        ['Modal', $report['ekuitas']['modal']],
                        ['Laba ditahan awal', $report['ekuitas']['laba_ditahan_awal']],
                        ['Laba ditahan', $report['ekuitas']['laba_ditahan']],
                        ['Total ekuitas', $report['total_ekuitas']],
                        ['Total pasiva', $report['total_pasiva']],
                        ['Balance check', $report['balance_check']],
                    ],
                ],
                [
                    'title' => 'Kas / Bank',
                    'headers' => ['Nama', 'Entitas', 'Saldo', 'Ref'],
                    'rows' => array_map($mapKas, $report['drilldown']['kas']),
                ],
                [
                    'title' => 'Piutang (jual − cash in tahun '.$report['year'].')',
                    'headers' => ['Nama', 'Entitas', 'Jual', 'Cash In', 'Piutang', 'Ref'],
                    'rows' => array_map($mapPiutang, $report['drilldown']['piutang']),
                ],
                [
                    'title' => 'Hutang (beli Supplier Umum tahun '.$report['year'].')',
                    'headers' => ['Nama', 'Entitas', 'Beli', 'Hutang', 'Ref'],
                    'rows' => array_map($mapHutang, $report['drilldown']['hutang']),
                ],
            ],
        );
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
    public function yearOptions(?\DateTimeInterface $now = null): array
    {
        $currentYear = (int) Carbon::parse($now ?? now())->year;
        $start = min(self::MIN_YEAR, $currentYear);

        return range($currentYear, $start);
    }

    /**
     * Current book value of fixed assets (not historical as-of).
     */
    public function aktivaTetap(): float
    {
        $fromWarehouses = (float) WarehouseItem::query()
            ->join('items', 'items.id', '=', 'warehouse_item.item_id')
            ->where('items.type', ItemType::ASSET_TETAP->value)
            ->selectRaw('COALESCE(SUM(warehouse_item.quantity * COALESCE(items.cost, 0)), 0) as amount')
            ->value('amount');

        if ($fromWarehouses > 0) {
            return $fromWarehouses;
        }

        return (float) Item::query()
            ->where('type', ItemType::ASSET_TETAP)
            ->selectRaw('COALESCE(SUM(COALESCE(qty, 0) * COALESCE(cost, 0)), 0) as amount')
            ->value('amount');
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function equity(int $entityId, bool $isConsolidated): array
    {
        $query = ReportingEntity::query()->where('is_active', true);
        if (! $isConsolidated) {
            $query->whereKey($entityId);
        }

        $entities = $query->get(['modal', 'laba_ditahan_awal']);

        return [
            (float) $entities->sum(fn (ReportingEntity $entity) => (float) ($entity->modal ?? 0)),
            (float) $entities->sum(fn (ReportingEntity $entity) => (float) ($entity->laba_ditahan_awal ?? 0)),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function scopeTradeRows(Collection $rows, bool $isConsolidated, int $entityId): Collection
    {
        return $rows
            ->filter(fn (object $row) => $this->contacts->include($row))
            ->when(
                ! $isConsolidated,
                fn (Collection $collection) => $collection->filter(
                    fn (object $row) => (int) $row->reporting_entity_id === $entityId
                ),
            )
            ->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  Collection<int|string, string>  $entityNames
     * @return list<array{id: int, name: string, balance: float, entity_name: string|null, sell?: float, cash_in?: float, buy?: float}>
     */
    private function formatDrilldown(Collection $rows, Collection $entityNames): array
    {
        return $rows
            ->sortBy(fn (object $row) => $row->name ?? '')
            ->map(function (object $row) use ($entityNames) {
                $item = [
                    'id' => (int) $row->customer_id,
                    'name' => (string) ($row->name ?? '#'.$row->customer_id),
                    'balance' => (float) $row->balance,
                    'entity_name' => $row->reporting_entity_id
                        ? ($entityNames[(int) $row->reporting_entity_id] ?? null)
                        : null,
                ];
                if (isset($row->sell) || isset($row->cash_in)) {
                    $item['sell'] = (float) ($row->sell ?? 0);
                    $item['cash_in'] = (float) ($row->cash_in ?? 0);
                }
                if (isset($row->buy)) {
                    $item['buy'] = (float) $row->buy;
                }

                return $item;
            })
            ->values()
            ->all();
    }
}
