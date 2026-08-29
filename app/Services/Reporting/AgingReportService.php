<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\Transaction;
use App\Services\Tax\ExpectedPaymentDateCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgingReportService
{
    public const CONSOLIDATED_ENTITY = 0;

    public const MIN_YEAR = 2025;

    public const KIND_RECEIVABLE = 'receivable';

    public const KIND_PAYABLE = 'payable';

    /**
     * @var list<string>
     */
    public const BUCKETS = ['0-30', '31-60', '61-90', '90+'];

    public function __construct(
        private readonly BalanceAsOfService $balances,
        private readonly ReportingContactFilter $contacts,
        private readonly ExpectedPaymentDateCalculator $dueDates,
    ) {}

    /**
     * @return array{
     *     kind: string,
     *     as_of: string,
     *     year: int,
     *     month: int,
     *     entity_id: int,
     *     entity_label: string,
     *     is_consolidated: bool,
     *     source: string,
     *     totals: array<string, float>,
     *     outstanding_total: float,
     *     rows: list<array<string, mixed>>,
     * }
     */
    public function build(string $kind, int $year, int $month, ?int $entityId, bool $refresh = false): array
    {
        $asOf = ReportingPeriod::asOf($year, $month);
        $isConsolidated = $entityId === null || $entityId === self::CONSOLIDATED_ENTITY;
        $resolvedEntityId = $isConsolidated ? self::CONSOLIDATED_ENTITY : (int) $entityId;
        $hadSnapshot = $this->balances->hasSnapshot($asOf->toDateString());

        $rows = $this->balances->balancesAsOf($asOf, persist: true, refresh: $refresh)
            ->filter(fn (object $row) => $this->contacts->include($row))
            ->values();

        $scoped = $isConsolidated
            ? $rows
            : $rows->filter(fn (object $row) => (int) $row->reporting_entity_id === $resolvedEntityId);

        $contacts = $kind === self::KIND_PAYABLE
            ? $this->contacts->payables($scoped)
            : $this->contacts->receivables($scoped);

        $entityNames = ReportingEntity::query()->pluck('name', 'id');
        $dueDays = $this->paymentDueDays($contacts->pluck('customer_id')->map(fn ($id) => (int) $id)->all());
        $invoices = $this->invoicesFor($kind, $contacts->pluck('customer_id')->map(fn ($id) => (int) $id)->all(), $asOf);

        $reportRows = [];
        $totals = array_fill_keys(self::BUCKETS, 0.0);

        foreach ($contacts as $contact) {
            $outstanding = abs((float) $contact->balance);
            if ($outstanding < 0.01) {
                continue;
            }

            $contactId = (int) $contact->customer_id;
            $dueDay = $dueDays[$contactId] ?? null;
            $allocated = $this->allocate($outstanding, $invoices->get($contactId, collect()), $dueDay, $asOf);

            $reportRows[] = [
                'id' => $contactId,
                'name' => (string) ($contact->name ?? '#'.$contactId),
                'entity_name' => $contact->reporting_entity_id
                    ? ($entityNames[(int) $contact->reporting_entity_id] ?? null)
                    : null,
                'balance' => (float) $contact->balance,
                'outstanding' => $outstanding,
                'payment_due_day' => $dueDay,
                'buckets' => $allocated['buckets'],
                'invoices' => $allocated['invoices'],
            ];

            foreach (self::BUCKETS as $bucket) {
                $totals[$bucket] += $allocated['buckets'][$bucket];
            }
        }

        usort($reportRows, fn (array $a, array $b) => $b['outstanding'] <=> $a['outstanding']);

        return [
            'kind' => $kind,
            'as_of' => $asOf->toDateString(),
            'year' => $year,
            'month' => $month,
            'entity_id' => $resolvedEntityId,
            'entity_label' => $this->entityLabel($resolvedEntityId),
            'is_consolidated' => $isConsolidated,
            'source' => ($hadSnapshot && ! $refresh) ? 'snapshot' : 'replay',
            'totals' => $totals,
            'outstanding_total' => array_sum($totals),
            'rows' => $reportRows,
        ];
    }

    public function exportCsv(array $report): StreamedResponse
    {
        $title = $report['kind'] === self::KIND_PAYABLE ? 'Hutang Usaha' : 'Piutang Usaha';
        $filename = sprintf(
            '%s-%s-%s.csv',
            $report['kind'] === self::KIND_PAYABLE ? 'hutang-aging' : 'piutang-aging',
            str($report['entity_label'])->slug(),
            $report['as_of'],
        );

        return new StreamedResponse(function () use ($report, $title) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$title, $report['entity_label'], 'as of '.$report['as_of']]);
            fputcsv($out, ['Kontak', 'Entitas', 'Due day', '0-30', '31-60', '61-90', '90+', 'Total']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['entity_name'],
                    $row['payment_due_day'],
                    $row['buckets']['0-30'],
                    $row['buckets']['31-60'],
                    $row['buckets']['61-90'],
                    $row['buckets']['90+'],
                    $row['outstanding'],
                ]);
            }
            fputcsv($out, [
                'Total',
                '',
                '',
                $report['totals']['0-30'],
                $report['totals']['31-60'],
                $report['totals']['61-90'],
                $report['totals']['90+'],
                $report['outstanding_total'],
            ]);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
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

    public function bucketForDays(int $days): string
    {
        $days = max(0, $days);

        if ($days <= 30) {
            return '0-30';
        }
        if ($days <= 60) {
            return '31-60';
        }
        if ($days <= 90) {
            return '61-90';
        }

        return '90+';
    }

    public function daysOverdue(Carbon $due, Carbon $asOf): int
    {
        $dueDay = $due->copy()->startOfDay();
        $asOfDay = $asOf->copy()->startOfDay();

        if ($dueDay->gt($asOfDay)) {
            return 0;
        }

        return (int) round($dueDay->diffInDays($asOfDay, false));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int|null>
     */
    private function paymentDueDays(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Addrbook::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->get(['id', 'payment_due_day'])
            ->mapWithKeys(fn (Addrbook $contact) => [
                (int) $contact->id => $contact->payment_due_day !== null ? (int) $contact->payment_due_day : null,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $contactIds
     * @return Collection<int, Collection<int, Transaction>>
     */
    private function invoicesFor(string $kind, array $contactIds, Carbon $asOf): Collection
    {
        if ($contactIds === []) {
            return collect();
        }

        $query = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('date', '<=', $asOf->toDateString())
            ->orderBy('date')
            ->orderBy('id');

        if ($kind === self::KIND_PAYABLE) {
            $query->where('type', Transaction::TYPE_BUY)->whereIn('sender_id', $contactIds);
            $groupBy = 'sender_id';
        } else {
            $query->where('type', Transaction::TYPE_SELL)->whereIn('receiver_id', $contactIds);
            $groupBy = 'receiver_id';
        }

        return $query
            ->get(['id', 'date', 'invoice', 'total', 'sender_id', 'receiver_id'])
            ->groupBy(fn (Transaction $transaction) => (int) $transaction->{$groupBy});
    }

    /**
     * Allocate the as-of outstanding to oldest invoices (FIFO). Remainder goes to 90+.
     *
     * @param  Collection<int, Transaction>  $invoices
     * @return array{buckets: array<string, float>, invoices: list<array<string, mixed>>}
     */
    private function allocate(float $outstanding, Collection $invoices, ?int $dueDay, Carbon $asOf): array
    {
        $buckets = array_fill_keys(self::BUCKETS, 0.0);
        $openInvoices = [];
        $remaining = $outstanding;

        foreach ($invoices as $invoice) {
            if ($remaining < 0.01) {
                break;
            }

            $invoiceAmount = abs((float) $invoice->total);
            if ($invoiceAmount < 0.01) {
                continue;
            }

            $openAmount = min($invoiceAmount, $remaining);
            $due = $this->dueDate($invoice->date, $dueDay);
            $days = $this->daysOverdue($due, $asOf);
            $bucket = $this->bucketForDays($days);

            $buckets[$bucket] += $openAmount;
            $remaining -= $openAmount;

            $openInvoices[] = [
                'id' => $invoice->id,
                'date' => $invoice->date->toDateString(),
                'invoice' => $invoice->invoice,
                'due_date' => $due->toDateString(),
                'days' => $days,
                'bucket' => $bucket,
                'amount' => $invoiceAmount,
                'open_amount' => $openAmount,
            ];
        }

        if ($remaining >= 0.01) {
            $buckets['90+'] += $remaining;
            $openInvoices[] = [
                'id' => 0,
                'date' => null,
                'invoice' => null,
                'due_date' => null,
                'days' => 91,
                'bucket' => '90+',
                'amount' => $remaining,
                'open_amount' => $remaining,
                'unallocated' => true,
            ];
        }

        return [
            'buckets' => $buckets,
            'invoices' => $openInvoices,
        ];
    }

    private function dueDate(Carbon $invoiceDate, ?int $dueDay): Carbon
    {
        return $this->dueDates->fromFakturDate($invoiceDate, $dueDay)
            ?? $invoiceDate->copy()->startOfDay();
    }
}
