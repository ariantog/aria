<?php

namespace App\Services\Reporting;

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingWarehouseFulfillment;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChannelPnlService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2025;

    public const KEY_UNALLOCATED = 'unallocated';

    public const KEY_UNMAPPED = 'unmapped';

    public function __construct(
        private readonly ChannelNameMatcher $matcher,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     period_start: string,
     *     period_end: string,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     source: string,
     *     rows: list<array<string, mixed>>,
     *     totals: array{pendapatan: float, marketplace_cost: float, toko_cost: float, kontribusi: float},
     *     notes: list<string>,
     *     mapping_warnings: array{fulfillment: bool, marketplace_ledgers: bool, toko_ledgers: bool},
     *     drilldown: array{pendapatan: list<array<string, mixed>>, biaya: list<array<string, mixed>>},
     * }
     */
    public function build(int $year, int $month, ?int $entityId): array
    {
        $isConsolidated = $entityId === null || $entityId === self::CONSOLIDATED_ENTITY;
        $resolvedEntityId = $isConsolidated ? self::CONSOLIDATED_ENTITY : (int) $entityId;
        [$periodStart, $periodEnd] = ReportingPeriod::monthRange($year, $month);

        $fulfillments = $this->fulfillmentRows();
        $marketplaceLedgers = $this->ledgersFor(ReportingLedgerRole::MarketplaceCost);
        $tokoLedgers = $this->ledgersFor(ReportingLedgerRole::TokoCost);
        $mappingWarnings = [
            'fulfillment' => $fulfillments->isEmpty(),
            'marketplace_ledgers' => $marketplaceLedgers === [],
            'toko_ledgers' => $tokoLedgers === [],
        ];

        if ($year < self::MIN_YEAR) {
            return $this->emptyReport($year, $month, $resolvedEntityId, $isConsolidated, $periodStart, $periodEnd, $mappingWarnings);
        }

        $scopedEntityId = $isConsolidated ? null : $resolvedEntityId;
        $bankIds = $this->entityBankIds($scopedEntityId);
        $warehouseNames = $this->warehouseNames($fulfillments);
        $channelCustomers = $this->channelCustomers($fulfillments, $marketplaceLedgers);
        $channelNames = $channelCustomers->mapWithKeys(
            fn (Addrbook $customer) => [(int) $customer->id => (string) $customer->name]
        )->all();
        $warehousesByChannel = $this->warehousesByChannel($fulfillments);

        $cashIn = $bankIds === [] ? collect() : $this->cashInRows($periodStart, $periodEnd, $bankIds);
        $marketplaceOut = $bankIds === [] || $marketplaceLedgers === []
            ? collect()
            : $this->cashOutRows($periodStart, $periodEnd, $bankIds, array_keys($marketplaceLedgers));
        $tokoOut = $bankIds === [] || $tokoLedgers === []
            ? collect()
            : $this->cashOutRows($periodStart, $periodEnd, $bankIds, array_keys($tokoLedgers));

        $pendapatan = [];
        $unmappedPendapatan = 0.0;
        $pendapatanDrill = [];
        $unmappedDrill = [];

        foreach ($cashIn as $row) {
            $senderId = (int) $row['sender_id'];
            if (isset($channelNames[$senderId])) {
                $pendapatan[$senderId] = ($pendapatan[$senderId] ?? 0) + $row['amount'];
                $pendapatanDrill[] = $this->drillRow($row, (string) $senderId, $channelNames[$senderId]);
            } else {
                $unmappedPendapatan += $row['amount'];
                $unmappedDrill[] = $this->drillRow($row, self::KEY_UNMAPPED, 'Pendapatan tidak terpetakan');
            }
        }

        $marketplace = [];
        $unallocatedMarketplace = 0.0;
        $biayaDrill = [];

        foreach ($marketplaceOut as $row) {
            $ledgerId = (int) $row['ledger_id'];
            $ledgerName = $marketplaceLedgers[$ledgerId] ?? $row['party'];
            $matched = $this->matcher->matchingIds($ledgerName, $channelNames);
            if ($matched === []) {
                $unallocatedMarketplace += $row['amount'];
                $biayaDrill[] = $this->costDrillRow($row, self::KEY_UNALLOCATED, 'Tidak teralokasi', 'marketplace');
                continue;
            }

            $weights = [];
            foreach ($matched as $channelId) {
                $weights[$channelId] = $pendapatan[$channelId] ?? 0.0;
            }
            foreach ($this->allocateAmount($row['amount'], $weights) as $channelId => $share) {
                $marketplace[$channelId] = ($marketplace[$channelId] ?? 0) + $share;
                $biayaDrill[] = $this->costDrillRow(
                    $row,
                    (string) $channelId,
                    $channelNames[$channelId] ?? 'Channel',
                    'marketplace',
                    $share,
                );
            }
        }

        $toko = [];
        $unallocatedToko = 0.0;
        $customersByWarehouse = $this->customersByWarehouse($fulfillments);

        foreach ($tokoOut as $row) {
            $ledgerId = (int) $row['ledger_id'];
            $ledgerName = $tokoLedgers[$ledgerId] ?? $row['party'];
            $matchedWarehouses = $this->matcher->matchingIds($ledgerName, $warehouseNames);
            $channelIds = [];
            foreach ($matchedWarehouses as $warehouseId) {
                foreach ($customersByWarehouse[$warehouseId] ?? [] as $channelId) {
                    if (isset($channelNames[$channelId])) {
                        $channelIds[$channelId] = $channelId;
                    }
                }
            }

            if ($channelIds === []) {
                $unallocatedToko += $row['amount'];
                $biayaDrill[] = $this->costDrillRow($row, self::KEY_UNALLOCATED, 'Tidak teralokasi', 'toko');
                continue;
            }

            $weights = [];
            foreach ($channelIds as $channelId) {
                $weights[$channelId] = $pendapatan[$channelId] ?? 0.0;
            }
            foreach ($this->allocateAmount($row['amount'], $weights) as $channelId => $share) {
                $toko[$channelId] = ($toko[$channelId] ?? 0) + $share;
                $biayaDrill[] = $this->costDrillRow(
                    $row,
                    (string) $channelId,
                    $channelNames[$channelId] ?? 'Channel',
                    'toko',
                    $share,
                );
            }
        }

        $rows = [];
        foreach ($channelCustomers as $customer) {
            $id = (int) $customer->id;
            $rev = round($pendapatan[$id] ?? 0.0, 2);
            $mp = round($marketplace[$id] ?? 0.0, 2);
            $tk = round($toko[$id] ?? 0.0, 2);
            $fromFulfillment = $fulfillments->contains(fn (ReportingWarehouseFulfillment $row) => (int) $row->customer_id === $id);

            if ($rev < 0.01 && $mp < 0.01 && $tk < 0.01 && ! $fromFulfillment) {
                continue;
            }

            $rows[] = $this->channelRow(
                (string) $id,
                $id,
                (string) $customer->name,
                'channel',
                $warehousesByChannel[$id] ?? [],
                $rev,
                $mp,
                $tk,
                $fromFulfillment,
            );
        }

        usort($rows, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        if ($unmappedPendapatan >= 0.01) {
            $rows[] = $this->channelRow(
                self::KEY_UNMAPPED,
                null,
                'Pendapatan tidak terpetakan',
                'unmapped',
                [],
                round($unmappedPendapatan, 2),
                0.0,
                0.0,
                false,
            );
        }

        if ($unallocatedMarketplace >= 0.01 || $unallocatedToko >= 0.01) {
            $rows[] = $this->channelRow(
                self::KEY_UNALLOCATED,
                null,
                'Tidak teralokasi',
                'unallocated',
                [],
                0.0,
                round($unallocatedMarketplace, 2),
                round($unallocatedToko, 2),
                false,
            );
        }

        $totals = [
            'pendapatan' => round(array_sum(array_column($rows, 'pendapatan')), 2),
            'marketplace_cost' => round(array_sum(array_column($rows, 'marketplace_cost')), 2),
            'toko_cost' => round(array_sum(array_column($rows, 'toko_cost')), 2),
            'kontribusi' => 0.0,
        ];
        $totals['kontribusi'] = round(
            $totals['pendapatan'] - $totals['marketplace_cost'] - $totals['toko_cost'],
            2,
        );

        return [
            'year' => $year,
            'month' => $month,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'entity_id' => $resolvedEntityId,
            'entity_label' => $this->entityLabel($resolvedEntityId),
            'is_consolidated' => $isConsolidated,
            'source' => 'bank_cash',
            'rows' => $rows,
            'totals' => $totals,
            'notes' => $this->notes(),
            'mapping_warnings' => $mappingWarnings,
            'drilldown' => [
                'pendapatan' => array_values(array_merge($pendapatanDrill, $unmappedDrill)),
                'biaya' => $biayaDrill,
            ],
        ];
    }

    public function exportCsv(array $report): StreamedResponse
    {
        $filename = $this->filename($report, 'csv');

        return new StreamedResponse(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Laporan Channel P&L', $report['entity_label'], $report['period_start'].' — '.$report['period_end']]);
            fputcsv($out, []);
            fputcsv($out, ['Channel', 'Gudang', 'Pendapatan', 'Biaya Marketplace', 'Biaya Toko', 'Kontribusi', 'Margin %']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    implode(', ', $row['warehouses']),
                    $row['pendapatan'],
                    $row['marketplace_cost'],
                    $row['toko_cost'],
                    $row['kontribusi'],
                    $row['margin'] === null ? '' : $row['margin'],
                ]);
            }
            fputcsv($out, [
                'Total',
                '',
                $report['totals']['pendapatan'],
                $report['totals']['marketplace_cost'],
                $report['totals']['toko_cost'],
                $report['totals']['kontribusi'],
                '',
            ]);
            fputcsv($out, []);
            fputcsv($out, ['Catatan']);
            foreach ($report['notes'] as $note) {
                fputcsv($out, [$note]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Drill-down pendapatan']);
            fputcsv($out, ['Tanggal', 'Channel', 'Pihak', 'Entitas', 'Jumlah', 'Ref']);
            foreach ($report['drilldown']['pendapatan'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['channel_name'],
                    $row['party'],
                    $row['entity_name'],
                    $row['amount'],
                    'tx:'.$row['id'],
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Drill-down biaya']);
            fputcsv($out, ['Tanggal', 'Jenis', 'Channel', 'Ledger', 'Entitas', 'Jumlah', 'Ref']);
            foreach ($report['drilldown']['biaya'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['cost_kind'] === 'toko' ? 'Biaya Toko' : 'Biaya Marketplace',
                    $row['channel_name'],
                    $row['party'],
                    $row['entity_name'],
                    $row['amount'],
                    'tx:'.$row['id'],
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportXlsx(array $report): StreamedResponse
    {
        return app(ReportingExcelExport::class)->download(
            $this->filename($report, 'xlsx'),
            'Channel P&L',
            [
                [
                    'title' => 'Laporan Channel P&L',
                    'rows' => [
                        ['Entitas', $report['entity_label']],
                        ['Periode', $report['period_start'].' — '.$report['period_end']],
                        ['Sumber', 'Cash In/Out bank (channel via fulfillment + ledger role)'],
                    ],
                ],
                [
                    'title' => 'Perbandingan channel',
                    'headers' => ['Channel', 'Gudang', 'Pendapatan', 'Biaya Marketplace', 'Biaya Toko', 'Kontribusi', 'Margin %'],
                    'rows' => array_merge(
                        array_map(fn (array $row) => [
                            $row['name'],
                            implode(', ', $row['warehouses']),
                            $row['pendapatan'],
                            $row['marketplace_cost'],
                            $row['toko_cost'],
                            $row['kontribusi'],
                            $row['margin'] === null ? '' : $row['margin'],
                        ], $report['rows']),
                        [[
                            'Total',
                            '',
                            $report['totals']['pendapatan'],
                            $report['totals']['marketplace_cost'],
                            $report['totals']['toko_cost'],
                            $report['totals']['kontribusi'],
                            '',
                        ]],
                    ),
                ],
                [
                    'title' => 'Catatan',
                    'rows' => array_map(fn (string $note) => [$note], $report['notes']),
                ],
                [
                    'title' => 'Drill-down pendapatan',
                    'headers' => ['Tanggal', 'Channel', 'Pihak', 'Entitas', 'Jumlah', 'Ref'],
                    'rows' => array_map(fn (array $row) => [
                        $row['date'],
                        $row['channel_name'],
                        $row['party'],
                        $row['entity_name'],
                        $row['amount'],
                        'tx:'.$row['id'],
                    ], $report['drilldown']['pendapatan']),
                ],
                [
                    'title' => 'Drill-down biaya',
                    'headers' => ['Tanggal', 'Jenis', 'Channel', 'Ledger', 'Entitas', 'Jumlah', 'Ref'],
                    'rows' => array_map(fn (array $row) => [
                        $row['date'],
                        $row['cost_kind'] === 'toko' ? 'Biaya Toko' : 'Biaya Marketplace',
                        $row['channel_name'],
                        $row['party'],
                        $row['entity_name'],
                        $row['amount'],
                        'tx:'.$row['id'],
                    ], $report['drilldown']['biaya']),
                ],
            ],
        );
    }

    public function yearOptions(?\DateTimeInterface $now = null): array
    {
        $currentYear = (int) Carbon::parse($now ?? now())->year;
        $start = min(self::MIN_YEAR, $currentYear);

        return range($currentYear, $start);
    }

    public function entityLabel(?int $entityId): string
    {
        if ($entityId === null || $entityId === self::CONSOLIDATED_ENTITY) {
            return 'Konsolidasi';
        }

        return ReportingEntity::query()->find($entityId)?->name ?? 'Entitas';
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return [
            'Pendapatan = Cash In dari customer/reseller channel ke bank entitas (bukan Sell / saldo kontak).',
            'Channel = customer di reporting_warehouse_fulfillment, atau nama yang cocok dengan ledger marketplace_cost.',
            'Biaya Marketplace = Cash Out ke ledger role marketplace_cost, dicocokkan ke channel lewat nama (Shopee ↔ Biaya Shopee). Beberapa channel yang cocok berbagi biaya proporsional pendapatan.',
            'Biaya Toko = Cash Out ke ledger role toko_cost, dicocokkan ke gudang lewat nama lalu dibagi ke channel fulfillment gudang itu (proporsional pendapatan; rata jika pendapatan 0).',
            'Internal lending dikeluarkan dari pendapatan. HPP tidak dihitung di laporan ini (lihat Laba Rugi konsolidasi).',
            'Cutover ringkasan pajak 2025-01-01; persediaan/HPP 2026-01-01 tidak dipakai di Channel P&L.',
        ];
    }

    /**
     * @param  array<int, float>  $weights
     * @return array<int, float>
     */
    public function allocateAmount(float $amount, array $weights): array
    {
        $amount = round($amount, 2);
        if ($amount == 0.0 || $weights === []) {
            return [];
        }

        $positive = [];
        foreach ($weights as $id => $weight) {
            if ($weight > 0.009) {
                $positive[(int) $id] = $weight;
            }
        }
        $use = $positive !== [] ? $positive : array_map(fn ($weight) => (float) $weight, $weights);
        $ids = array_keys($use);
        $totalWeight = array_sum($use);
        if ($totalWeight <= 0) {
            $share = round($amount / count($ids), 2);
            $out = [];
            $assigned = 0.0;
            foreach ($ids as $index => $id) {
                if ($index === array_key_last($ids)) {
                    $out[(int) $id] = round($amount - $assigned, 2);
                } else {
                    $out[(int) $id] = $share;
                    $assigned += $share;
                }
            }

            return $out;
        }

        $out = [];
        $assigned = 0.0;
        foreach ($ids as $index => $id) {
            if ($index === array_key_last($ids)) {
                $out[(int) $id] = round($amount - $assigned, 2);
            } else {
                $part = round($amount * ($use[$id] / $totalWeight), 2);
                $out[(int) $id] = $part;
                $assigned += $part;
            }
        }

        return $out;
    }

    /**
     * @return Collection<int, ReportingWarehouseFulfillment>
     */
    private function fulfillmentRows(): Collection
    {
        return ReportingWarehouseFulfillment::query()
            ->with([
                'warehouse' => fn ($query) => $query->withTrashed()->select('id', 'name'),
                'customer' => fn ($query) => $query->withTrashed()->select('id', 'name', 'type', 'is_internal_lending'),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function ledgersFor(ReportingLedgerRole $role): array
    {
        $ids = ReportingLedgerRoleModel::customerIdsFor($role);
        if ($ids === []) {
            return [];
        }

        return Addrbook::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * @param  Collection<int, ReportingWarehouseFulfillment>  $fulfillments
     * @param  array<int, string>  $marketplaceLedgers
     * @return Collection<int, Addrbook>
     */
    private function channelCustomers(Collection $fulfillments, array $marketplaceLedgers): Collection
    {
        $ids = $fulfillments
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $salesChannelIds = Addrbook::query()
            ->whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->where('reporting_role', 'sales_channel')
            ->where(fn ($query) => $query->where('is_internal_lending', false)->orWhereNull('is_internal_lending'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ledgerTokens = [];
        foreach ($marketplaceLedgers as $ledgerName) {
            foreach ($this->matcher->tokens($ledgerName) as $token) {
                $ledgerTokens[$token] = $token;
            }
        }

        if ($ledgerTokens !== []) {
            $candidates = Addrbook::query()
                ->whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
                ->where(fn ($query) => $query->where('is_internal_lending', false)->orWhereNull('is_internal_lending'))
                ->where(function ($query) use ($ledgerTokens) {
                    foreach ($ledgerTokens as $token) {
                        $query->orWhere('name', 'like', '%'.$token.'%');
                    }
                })
                ->get(['id', 'name', 'type']);

            foreach ($candidates as $candidate) {
                foreach ($marketplaceLedgers as $ledgerName) {
                    if ($this->matcher->score((string) $candidate->name, $ledgerName) > 0) {
                        $ids[] = (int) $candidate->id;
                        break;
                    }
                }
            }
        }

        $ids = array_values(array_unique(array_merge($ids, $salesChannelIds)));
        if ($ids === []) {
            return collect();
        }

        return Addrbook::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->where(fn ($query) => $query->where('is_internal_lending', false)->orWhereNull('is_internal_lending'))
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /**
     * @param  Collection<int, ReportingWarehouseFulfillment>  $fulfillments
     * @return array<int, string>
     */
    private function warehouseNames(Collection $fulfillments): array
    {
        $names = [];
        foreach ($fulfillments as $row) {
            $warehouseId = (int) $row->warehouse_id;
            $names[$warehouseId] = $row->warehouse?->name ?? '';
        }

        return $names;
    }

    /**
     * @param  Collection<int, ReportingWarehouseFulfillment>  $fulfillments
     * @return array<int, list<string>>
     */
    private function warehousesByChannel(Collection $fulfillments): array
    {
        $map = [];
        foreach ($fulfillments as $row) {
            $channelId = (int) $row->customer_id;
            $name = $row->warehouse?->name;
            if ($name === null || $name === '') {
                continue;
            }
            $map[$channelId] ??= [];
            if (! in_array($name, $map[$channelId], true)) {
                $map[$channelId][] = $name;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, ReportingWarehouseFulfillment>  $fulfillments
     * @return array<int, list<int>>
     */
    private function customersByWarehouse(Collection $fulfillments): array
    {
        $map = [];
        foreach ($fulfillments as $row) {
            $warehouseId = (int) $row->warehouse_id;
            $channelId = (int) $row->customer_id;
            $map[$warehouseId] ??= [];
            if (! in_array($channelId, $map[$warehouseId], true)) {
                $map[$warehouseId][] = $channelId;
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $bankIds
     * @return Collection<int, array<string, mixed>>
     */
    private function cashInRows(string $start, string $end, array $bankIds): Collection
    {
        $lendingIds = $this->internalLendingContactIds();

        $rows = Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_IN)
            ->where('receiver_type', Addrbook::TYPE_BANK)
            ->whereIn('sender_type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
            ->whereIn('receiver_id', $bankIds)
            ->when($lendingIds !== [], fn ($query) => $query->whereNotIn('sender_id', $lendingIds))
            ->whereBetween('date', ReportingPeriod::queryBounds($start, $end))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $rows->map(function (Transaction $transaction) {
            $entity = ReportingEntity::findActiveForBank((int) $transaction->receiver_id);

            return [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'invoice' => $transaction->invoice,
                'sender_id' => (int) $transaction->sender_id,
                'party' => $transaction->sender?->name ?? '—',
                'entity_name' => $entity?->name,
                'amount' => $this->transactionAmount($transaction),
            ];
        });
    }

    /**
     * @param  list<int>  $bankIds
     * @param  list<int>  $ledgerIds
     * @return Collection<int, array<string, mixed>>
     */
    private function cashOutRows(string $start, string $end, array $bankIds, array $ledgerIds): Collection
    {
        if ($ledgerIds === []) {
            return collect();
        }

        $rows = Transaction::query()
            ->with(['sender', 'receiver'])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_OUT)
            ->where('sender_type', Addrbook::TYPE_BANK)
            ->where('receiver_type', Addrbook::TYPE_ACCOUNT)
            ->whereIn('sender_id', $bankIds)
            ->whereIn('receiver_id', $ledgerIds)
            ->whereBetween('date', ReportingPeriod::queryBounds($start, $end))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $rows->map(function (Transaction $transaction) {
            $entity = ReportingEntity::findActiveForBank((int) $transaction->sender_id);

            return [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'invoice' => $transaction->invoice,
                'ledger_id' => (int) $transaction->receiver_id,
                'party' => $transaction->receiver?->name ?? '—',
                'entity_name' => $entity?->name,
                'amount' => $this->transactionAmount($transaction),
            ];
        });
    }

    /**
     * @return list<int>
     */
    private function internalLendingContactIds(): array
    {
        return Addrbook::query()
            ->withTrashed()
            ->where('is_internal_lending', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function entityBankIds(?int $entityId): array
    {
        $query = DB::table('reporting_entity_banks')->where('is_active', true);

        if ($entityId !== null && $entityId !== self::CONSOLIDATED_ENTITY) {
            $query->where('reporting_entity_id', $entityId);
        }

        return $query->pluck('bank_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function drillRow(array $row, string $channelKey, string $channelName): array
    {
        return [
            'id' => $row['id'],
            'date' => $row['date'],
            'invoice' => $row['invoice'],
            'party' => $row['party'],
            'entity_name' => $row['entity_name'],
            'amount' => $row['amount'],
            'channel_key' => $channelKey,
            'channel_name' => $channelName,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function costDrillRow(array $row, string $channelKey, string $channelName, string $costKind, ?float $amount = null): array
    {
        return [
            'id' => $row['id'],
            'date' => $row['date'],
            'invoice' => $row['invoice'],
            'party' => $row['party'],
            'entity_name' => $row['entity_name'],
            'amount' => $amount ?? $row['amount'],
            'channel_key' => $channelKey,
            'channel_name' => $channelName,
            'cost_kind' => $costKind,
        ];
    }

    /**
     * @param  list<string>  $warehouses
     * @return array<string, mixed>
     */
    private function channelRow(
        string $key,
        ?int $customerId,
        string $name,
        string $kind,
        array $warehouses,
        float $pendapatan,
        float $marketplace,
        float $toko,
        bool $fromFulfillment,
    ): array {
        $kontribusi = round($pendapatan - $marketplace - $toko, 2);

        return [
            'key' => $key,
            'customer_id' => $customerId,
            'name' => $name,
            'kind' => $kind,
            'warehouses' => $warehouses,
            'pendapatan' => $pendapatan,
            'marketplace_cost' => $marketplace,
            'toko_cost' => $toko,
            'kontribusi' => $kontribusi,
            'margin' => $pendapatan >= 0.01 ? round(($kontribusi / $pendapatan) * 100, 1) : null,
            'from_fulfillment' => $fromFulfillment,
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     period_start: string,
     *     period_end: string,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     source: string,
     *     rows: list<array<string, mixed>>,
     *     totals: array{pendapatan: float, marketplace_cost: float, toko_cost: float, kontribusi: float},
     *     notes: list<string>,
     *     mapping_warnings: array{fulfillment: bool, marketplace_ledgers: bool, toko_ledgers: bool},
     *     drilldown: array{pendapatan: list<array<string, mixed>>, biaya: list<array<string, mixed>>},
     * }
     */
    private function emptyReport(
        int $year,
        int $month,
        int $entityId,
        bool $isConsolidated,
        string $periodStart,
        string $periodEnd,
        array $mappingWarnings,
    ): array {
        return [
            'year' => $year,
            'month' => $month,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'entity_id' => $entityId,
            'entity_label' => $this->entityLabel($entityId),
            'is_consolidated' => $isConsolidated,
            'source' => 'bank_cash',
            'rows' => [],
            'totals' => [
                'pendapatan' => 0.0,
                'marketplace_cost' => 0.0,
                'toko_cost' => 0.0,
                'kontribusi' => 0.0,
            ],
            'notes' => $this->notes(),
            'mapping_warnings' => $mappingWarnings,
            'drilldown' => [
                'pendapatan' => [],
                'biaya' => [],
            ],
        ];
    }

    private function filename(array $report, string $extension): string
    {
        return sprintf(
            'channel-pnl-%s-%04d-%02d.%s',
            str($report['entity_label'])->slug(),
            $report['year'],
            $report['month'],
            $extension,
        );
    }

    private function transactionAmount(Transaction $transaction): float
    {
        return abs((float) $transaction->total);
    }
}
