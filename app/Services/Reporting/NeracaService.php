<?php

namespace App\Services\Reporting;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ReportingEntity;
use App\Models\WarehouseItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NeracaService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2025;

    public function __construct(
        private readonly InventoryRollForwardService $inventory,
        private readonly BalanceAsOfService $balances,
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
        $rows = $this->balances->balancesAsOf($asOf, persist: true, refresh: $refresh)
            ->filter(fn (object $row) => $this->includeContact($row))
            ->values();

        $scoped = $isConsolidated
            ? $rows
            : $rows->filter(fn (object $row) => (int) $row->reporting_entity_id === $resolvedEntityId);

        $kasRows = $scoped->where('customer_type', Addrbook::TYPE_BANK)->values();
        $piutangRows = $this->receivableRows($isConsolidated ? $rows : $scoped);
        $hutangRows = $this->payableRows($isConsolidated ? $rows : $scoped);

        $kas = (float) $kasRows->sum('balance');
        $piutang = (float) $piutangRows->sum(fn (object $row) => abs((float) $row->balance));
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

    private function includeContact(object $row): bool
    {
        if (! empty($row->is_internal_lending)) {
            return false;
        }

        return $row->is_active_in_reports !== false;
    }

    /**
     * Negative customer/reseller balances = they owe us (piutang).
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function receivableRows(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (object $row) => in_array((int) $row->customer_type, [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER], true))
            ->filter(fn (object $row) => (float) $row->balance < 0)
            ->values();
    }

    /**
     * Positive supplier balances = we owe them (hutang).
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function payableRows(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (object $row) => (int) $row->customer_type === Addrbook::TYPE_SUPPLIER)
            ->filter(fn (object $row) => (float) $row->balance > 0)
            ->values();
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
     * @param  Collection<int|string, string>  $entityNames
     * @return list<array{id: int, name: string, balance: float, entity_name: string|null}>
     */
    private function formatDrilldown(Collection $rows, Collection $entityNames): array
    {
        return $rows
            ->sortBy(fn (object $row) => $row->name ?? '')
            ->map(fn (object $row) => [
                'id' => (int) $row->customer_id,
                'name' => (string) ($row->name ?? '#'.$row->customer_id),
                'balance' => (float) $row->balance,
                'entity_name' => $row->reporting_entity_id
                    ? ($entityNames[(int) $row->reporting_entity_id] ?? null)
                    : null,
            ])
            ->values()
            ->all();
    }
}
