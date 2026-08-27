<?php

namespace App\Services\Tax;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\TaxFakturImport;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostFakturSell
{
    public const DATE_SOURCE_FAKTUR = 'faktur';

    public const DATE_SOURCE_CASH_IN = 'cash_in';

    public const INVOICE_SOURCE_FAKTUR = 'faktur';

    public const INVOICE_SOURCE_CASH_IN = 'cash_in';

    public const LINE_MODE_SUMMARY = 'summary';

    public const LINE_MODE_MAPPED = 'mapped';

    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * @param  array{
     *     warehouse_id: int,
     *     date_source?: string,
     *     invoice_source?: string,
     *     line_mode?: string,
     *     summary_item_id?: int|null,
     *     mapped_lines?: list<array{line_no: int, item_id: int}>|null,
     * }  $options
     */
    public function execute(TaxFakturImport $import, array $options): Transaction
    {
        $import = $import->fresh(['counterparty', 'cashInTransaction']);

        $this->assertCanPost($import);

        $warehouse = Addrbook::query()->findOrFail($options['warehouse_id']);
        if (! Addrbook::typeIsWarehouse((int) $warehouse->type)) {
            throw new InvalidArgumentException('Warehouse must be a warehouse or consignment location.');
        }

        $customer = $import->counterparty;
        if (! $customer) {
            throw new InvalidArgumentException('Faktur counterparty is missing.');
        }

        $lineMode = $options['line_mode'] ?? self::LINE_MODE_SUMMARY;
        $detailRows = $lineMode === self::LINE_MODE_MAPPED
            ? $this->buildMappedDetails($import, $options['mapped_lines'] ?? [])
            : $this->buildSummaryDetails($import, (int) ($options['summary_item_id'] ?? 0));

        $this->assertStockAvailable($warehouse, $detailRows);

        $date = $this->resolveDate($import, $options['date_source'] ?? self::DATE_SOURCE_FAKTUR);
        $invoice = $this->resolveInvoice($import, $options['invoice_source'] ?? self::INVOICE_SOURCE_FAKTUR);

        $dpp = (float) $import->dpp;
        $ppn = (float) $import->ppn;
        $grandTotal = $dpp + $ppn + (float) $import->ppnbm;
        $totalItems = array_sum(array_column($detailRows, 'quantity'));

        return DB::transaction(function () use (
            $import,
            $warehouse,
            $customer,
            $detailRows,
            $date,
            $invoice,
            $dpp,
            $ppn,
            $grandTotal,
            $totalItems,
        ) {
            $transaction = Transaction::create([
                'date' => $date,
                'type' => Transaction::TYPE_SELL,
                'sender_type' => (int) $warehouse->type,
                'sender_id' => $warehouse->id,
                'receiver_type' => (int) $customer->type,
                'receiver_id' => $customer->id,
                'invoice' => $invoice,
                'notes' => sprintf('Sell from faktur %s', $import->faktur_number),
                'user_id' => Auth::id(),
                'status' => Transaction::STATUS_COMPLETED,
                'total' => Transaction::signedAmount(Transaction::TYPE_SELL, $dpp),
                'real_total' => Transaction::signedAmount(Transaction::TYPE_SELL, $grandTotal),
                'ppn' => $ppn,
                'discount' => 0,
                'adjustment' => 0,
                'total_items' => $totalItems,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            ]);

            if (empty($transaction->invoice)) {
                $transaction->update(['invoice' => (string) $transaction->id]);
            }

            foreach ($detailRows as $row) {
                $transaction->details()->create([
                    'item_id' => $row['item_id'],
                    'date' => $transaction->date,
                    'transaction_type' => Transaction::TYPE_SELL,
                    'sender_id' => $warehouse->id,
                    'receiver_id' => $customer->id,
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'discount' => 0,
                    'total' => $row['total'],
                    'notes' => $row['note'] ?? null,
                ]);
            }

            $this->transactionService->handleTransaction($transaction);

            $import->sell_transaction_id = $transaction->id;
            $import->save();

            return $transaction->fresh(['details', 'sender', 'receiver']);
        });
    }

    private function assertCanPost(TaxFakturImport $import): void
    {
        if ($import->sell_transaction_id) {
            throw new InvalidArgumentException('Sell has already been posted for this faktur import.');
        }

        if ($import->direction !== TaxFakturImport::DIRECTION_KELUARAN) {
            throw new InvalidArgumentException('Only keluaran faktur imports can post a Sell.');
        }

        if (! $import->isConsignmentCounterparty()) {
            throw new InvalidArgumentException('Counterparty must be a consignment customer with a payment due day (e.g. MDS/Central).');
        }

        if (! $import->hasPaymentInfo()) {
            throw new InvalidArgumentException('Link a Cash In or record payment amount and date before posting Sell.');
        }
    }

    /**
     * @return list<array{item_id: int, quantity: float, price: float, total: float, note?: string}>
     */
    private function buildSummaryDetails(TaxFakturImport $import, int $summaryItemId): array
    {
        if ($summaryItemId <= 0) {
            throw new InvalidArgumentException('Select an item for the summary Sell line.');
        }

        $item = Item::query()->find($summaryItemId);
        if (! $item) {
            throw new InvalidArgumentException('Summary item does not exist.');
        }

        $dpp = (float) $import->dpp;
        if ($dpp <= 0) {
            throw new InvalidArgumentException('Faktur DPP must be greater than zero.');
        }

        return [[
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => $dpp,
            'total' => $dpp,
            'note' => 'Summary line — faktur '.$import->faktur_number,
        ]];
    }

    /**
     * @param  list<array{line_no: int, item_id: int}>  $mappedLines
     * @return list<array{item_id: int, quantity: float, price: float, total: float, note?: string}>
     */
    private function buildMappedDetails(TaxFakturImport $import, array $mappedLines): array
    {
        if ($mappedLines === []) {
            throw new InvalidArgumentException('Provide item mappings for each faktur line or use summary mode.');
        }

        $lineItems = collect($import->line_items ?? [])->keyBy(fn ($line, $index) => (int) ($line['line_no'] ?? ($index + 1)));
        $details = [];

        foreach ($mappedLines as $mapping) {
            $lineNo = (int) ($mapping['line_no'] ?? 0);
            $itemId = (int) ($mapping['item_id'] ?? 0);
            if ($lineNo <= 0 || $itemId <= 0) {
                throw new InvalidArgumentException('Each mapped line requires line_no and item_id.');
            }

            $line = $lineItems->get($lineNo);
            if (! $line) {
                throw new InvalidArgumentException("Faktur line {$lineNo} was not found.");
            }

            $item = Item::query()->find($itemId);
            if (! $item) {
                throw new InvalidArgumentException("Item for faktur line {$lineNo} does not exist.");
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $total = (float) ($line['total'] ?? ($quantity * $unitPrice));

            if ($quantity <= 0) {
                throw new InvalidArgumentException("Faktur line {$lineNo} has invalid quantity.");
            }

            $details[] = [
                'item_id' => $item->id,
                'quantity' => $quantity,
                'price' => $unitPrice > 0 ? $unitPrice : ($quantity > 0 ? round($total / $quantity, 2) : 0),
                'total' => $total,
                'note' => trim((string) ($line['name'] ?? '')) ?: null,
            ];
        }

        $mappedDpp = round(array_sum(array_column($details, 'total')), 2);
        $fakturDpp = round((float) $import->dpp, 2);
        if (abs($mappedDpp - $fakturDpp) > 0.02) {
            throw new InvalidArgumentException(
                sprintf('Mapped line totals (Rp %s) do not match faktur DPP (Rp %s). Use summary mode or fix mappings.', number_format($mappedDpp, 2, '.', ''), number_format($fakturDpp, 2, '.', ''))
            );
        }

        return $details;
    }

    /**
     * @param  list<array{item_id: int, quantity: float}>  $detailRows
     */
    private function assertStockAvailable(Addrbook $warehouse, array $detailRows): void
    {
        if (Addrbook::typeAllowsNegativeStock((int) $warehouse->type)) {
            return;
        }

        $insufficient = [];
        foreach ($detailRows as $row) {
            $wi = WarehouseItem::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('item_id', $row['item_id'])
                ->first();
            $available = $wi ? (float) $wi->quantity : 0;
            if ((float) $row['quantity'] > $available) {
                $item = Item::query()->find($row['item_id']);
                $insufficient[] = ($item?->name ?? 'ID '.$row['item_id'])." (avail: {$available}, need: {$row['quantity']})";
            }
        }

        if ($insufficient !== []) {
            throw new InvalidArgumentException('Insufficient stock: '.implode('; ', $insufficient));
        }
    }

    private function resolveDate(TaxFakturImport $import, string $source): string
    {
        if ($source === self::DATE_SOURCE_CASH_IN && $import->cashInTransaction?->date) {
            return $import->cashInTransaction->date->toDateString();
        }

        if ($import->payment_received_date && $source === self::DATE_SOURCE_CASH_IN) {
            return $import->payment_received_date->toDateString();
        }

        if ($import->faktur_date) {
            return $import->faktur_date->toDateString();
        }

        return now()->toDateString();
    }

    private function resolveInvoice(TaxFakturImport $import, string $source): ?string
    {
        if ($source === self::INVOICE_SOURCE_CASH_IN) {
            $cashInInvoice = $import->cashInTransaction?->invoice;
            if (filled($cashInInvoice)) {
                return $cashInInvoice;
            }
        }

        return $import->faktur_number;
    }
}
