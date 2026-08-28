<?php

namespace App\Actions\Jubelio;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use App\Services\Jubelio\JubelioOrderPayloadService;
use App\Services\Jubelio\JubelioSellerIncomeResolver;
use App\Services\LocationAccessService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessJubelioOrder
{
    public function __construct(
        private TransactionService $transactionService,
        private JubelioOrderPayloadService $payloadService,
        private LocationAccessService $locationAccessService,
        private JubelioSellerIncomeResolver $sellerIncomeResolver,
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(Jubelioorder $order, ?int $executedByUserId = null): array
    {
        Log::info("Memproses Jubelioorder ID: {$order->id}, Type: {$order->type}, Invoice: {$order->invoice}");

        $order->refresh();
        $runCount = $order->run_count + 1;
        $dataApi = $this->resolvePayload($order);

        if ($dataApi === []) {
            $order->update([
                'run_count' => $runCount,
                'error_type' => 3,
                'error' => 'Payload JSON tidak valid',
                'stock_error_items' => null,
                'status' => 1,
            ]);

            return ['success' => false, 'message' => 'Payload JSON tidak valid'];
        }

        if ($order->type === 'SELL') {
            return $this->processSell($order, $dataApi, $runCount, $executedByUserId);
        }

        if ($order->type === 'RETURN') {
            return $this->processReturn($order, $dataApi, $runCount, $executedByUserId);
        }

        return ['success' => false, 'message' => 'Tipe order tidak didukung'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePayload(Jubelioorder $order): array
    {
        return $this->payloadService->fetchOrEmpty($order);
    }

    /**
     * @param  array<string, mixed>  $dataApi
     * @return array{success: bool, message: string}
     */
    protected function processSell(Jubelioorder $order, array $dataApi, int $runCount, ?int $executedByUserId): array
    {
        $jubelioSync = Jubeliosync::where('jubelio_store_id', $dataApi['store_id'])
            ->where('jubelio_location_id', $dataApi['location_id'])
            ->first();

        if (! $jubelioSync) {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 1,
                'error' => 'Data sync store/location ID tidak ditemukan',
                'stock_error_items' => null,
                'status' => 1,
            ], $this->warehousePersistFields($dataApi)));

            return ['success' => false, 'message' => 'Data sync store/location ID tidak ditemukan'];
        }

        $warehouseId = (int) $jubelioSync->warehouse_id;

        $matched = $this->matchItems($dataApi['items'] ?? [], 'qty');

        if ($matched['error']) {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 1,
                'error' => $matched['error'],
                'stock_error_items' => null,
                'status' => 1,
            ], $this->warehousePersistFields($dataApi, $warehouseId)));

            return ['success' => false, 'message' => $matched['error']];
        }

        $arrayInvoice = $dataApi['salesorder_no'] ?? $order->invoice;

        if (Transaction::where('type', Transaction::TYPE_SELL)->where('invoice', $arrayInvoice)->exists()) {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 2,
                'error' => 'Transaction sudah ada',
                'stock_error_items' => null,
                'status' => 2,
            ], $this->warehousePersistFields($dataApi, $warehouseId)));

            return ['success' => false, 'message' => 'Transaction sudah ada'];
        }

        $stockError = $this->validateWarehouseStock($warehouseId, $matched['items']);
        if ($stockError !== null) {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 1,
                'error' => $stockError['message'],
                'stock_error_items' => $stockError['items'],
                'status' => 1,
            ], $this->warehousePersistFields($dataApi, $warehouseId)));

            return ['success' => false, 'message' => $stockError['message']];
        }

        $createData = $this->createTransaction(Transaction::TYPE_SELL, (object) [
            'date' => $dataApi['transaction_date'] ?? now()->toDateString(),
            'warehouse' => $jubelioSync->warehouse_id,
            'customer' => $jubelioSync->customer_id,
            'invoice' => $arrayInvoice,
            'description' => (string) $arrayInvoice,
            'note' => $executedByUserId ? 'generated manually from aria' : 'generated by cron aria',
            'userId' => $executedByUserId,
            'account' => '7204',
            'addMoreInputFields' => $matched['items'],
            'disc' => '0',
            'adjustment' => $this->resolveLineAdjustment($matched['items'], $dataApi),
            'ongkir' => '0',
        ]);

        if ($createData['status'] === '200') {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 10,
                'error' => null,
                'stock_error_items' => null,
                'status' => 2,
                'execute_by' => $executedByUserId ?? 0,
            ], $this->warehousePersistFields($dataApi, $warehouseId)));
            Log::info("Berhasil Sell: {$arrayInvoice}");

            return ['success' => true, 'message' => 'Transaksi berhasil dibuat'];
        }

        $order->update(array_merge([
            'run_count' => $runCount,
            'error_type' => 1,
            'error' => $createData['message'],
            'stock_error_items' => null,
            'status' => 1,
        ], $this->warehousePersistFields($dataApi, $warehouseId)));

        return ['success' => false, 'message' => $createData['message']];
    }

    /**
     * @param  array<string, mixed>  $dataApi
     * @return array{success: bool, message: string}
     */
    protected function processReturn(Jubelioorder $order, array $dataApi, int $runCount, ?int $executedByUserId): array
    {
        $cekTransaksiSell = Transaction::where('type', Transaction::TYPE_SELL)
            ->where('invoice', $dataApi['salesorder_no'])
            ->first();

        if (! $cekTransaksiSell) {
            $order->update([
                'run_count' => $runCount,
                'error_type' => 3,
                'error' => 'Transaksi jual (asal) tidak ditemukan untuk retur ini',
                'stock_error_items' => null,
                'status' => 1,
            ]);

            return ['success' => false, 'message' => 'Transaksi jual (asal) tidak ditemukan untuk retur ini'];
        }

        $warehouseId = (int) $cekTransaksiSell->sender_id;

        $matched = $this->matchItems($dataApi['items'] ?? [], 'qty_in_base');

        if ($matched['error']) {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 1,
                'error' => $matched['error'],
                'stock_error_items' => null,
                'status' => 1,
            ], $this->warehousePersistFields($dataApi, $warehouseId)));

            return ['success' => false, 'message' => $matched['error']];
        }

        if (Transaction::where('type', Transaction::TYPE_RETURN)->where('invoice', $dataApi['return_no'])->exists()) {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 2,
                'error' => 'Invoice Retur sudah ada',
                'stock_error_items' => null,
                'status' => 2,
            ], $this->warehousePersistFields($dataApi, $warehouseId)));

            return ['success' => false, 'message' => 'Invoice Retur sudah ada'];
        }

        $createData = $this->createTransaction(Transaction::TYPE_RETURN, (object) [
            'date' => $dataApi['transaction_date'] ?? now()->toDateString(),
            'warehouse' => $cekTransaksiSell->sender_id,
            'customer' => $cekTransaksiSell->receiver_id,
            'invoice' => $dataApi['return_no'],
            'description' => $dataApi['salesorder_no'],
            'note' => $executedByUserId ? 'generated manually from aria' : 'generated by jubelio',
            'userId' => $executedByUserId,
            'account' => '7204',
            'addMoreInputFields' => $matched['items'],
            'disc' => '0',
            'adjustment' => $this->resolveLineAdjustment($matched['items'], $dataApi),
            'ongkir' => '0',
        ]);

        if ($createData['status'] === '200') {
            $order->update(array_merge([
                'run_count' => $runCount,
                'error_type' => 10,
                'error' => null,
                'stock_error_items' => null,
                'status' => 2,
                'execute_by' => $executedByUserId ?? 0,
            ], $this->warehousePersistFields($dataApi, $warehouseId)));
            Log::info('Berhasil Return: '.$dataApi['return_no']);

            return ['success' => true, 'message' => 'Transaksi retur berhasil dibuat'];
        }

        $order->update(array_merge([
            'run_count' => $runCount,
            'error_type' => 1,
            'error' => $createData['message'],
            'stock_error_items' => null,
            'status' => 1,
        ], $this->warehousePersistFields($dataApi, $warehouseId)));

        return ['success' => false, 'message' => $createData['message']];
    }

    /**
     * @param  array<string, mixed>  $dataApi
     * @return array{warehouse_id: int, jubelio_store_id: int, jubelio_location_id: int}
     */
    private function warehousePersistFields(array $dataApi, int $warehouseId = 0): array
    {
        return [
            'warehouse_id' => $warehouseId,
            'jubelio_store_id' => (int) ($dataApi['store_id'] ?? 0),
            'jubelio_location_id' => (int) ($dataApi['location_id'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $arrayItems
     * @return array{error: ?string, items: list<array<string, mixed>>}
     */
    protected function matchItems(array $arrayItems, string $qtyKey): array
    {
        $itemCodes = collect($arrayItems)->pluck('item_code')->unique()->all();
        $existingProducts = Item::findManyBySkus($itemCodes);

        $groupedData = collect($arrayItems)->partition(
            fn ($item) => isset($existingProducts[strtoupper($item['item_code'])])
        );

        $notMatched = $groupedData[1];

        if ($notMatched->count() > 0) {
            return [
                'error' => 'SKU tidak ditemukan: '.implode(', ', $notMatched->pluck('item_code')->toArray()),
                'items' => [],
            ];
        }

        $items = $groupedData[0]->map(function ($item) use ($existingProducts, $qtyKey) {
            $product = $existingProducts[strtoupper($item['item_code'])];
            $qty = (float) $item[$qtyKey];
            $lineTotal = $this->resolveItemLineTotal($item, $qty);

            return [
                'itemId' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'quantity' => $qty,
                'price' => $qty > 0 ? $lineTotal / $qty : (float) ($item['price'] ?? 0),
                'discount' => 0,
                'subtotal' => $lineTotal,
            ];
        })->values()->all();

        return ['error' => null, 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveItemLineTotal(array $item, float $qty): float
    {
        if (isset($item['amount'])) {
            return (float) $item['amount'];
        }

        if (isset($item['total'])) {
            return (float) $item['total'];
        }

        return $qty * (float) ($item['price'] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $matchedItems
     * @param  array<string, mixed>  $dataApi
     */
    protected function resolveLineAdjustment(array $matchedItems, array $dataApi): float
    {
        $itemsTotal = (float) collect($matchedItems)->sum('subtotal');
        $receivable = $this->sellerIncomeResolver->resolve($dataApi, $itemsTotal);

        return $receivable - $itemsTotal;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{message: string, items: list<array{item_id: int, code: string, available: float, needed: float}>}|null
     */
    protected function validateWarehouseStock(int $warehouseId, array $items): ?array
    {
        $warehouse = Addrbook::find($warehouseId);
        if (! $warehouse || ! Addrbook::typeIsWarehouse((int) $warehouse->type)) {
            return null;
        }

        if (Addrbook::typeAllowsNegativeStock((int) $warehouse->type)) {
            return null;
        }

        $insufficient = [];
        foreach ($items as $item) {
            $wi = WarehouseItem::query()
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $item['itemId'])
                ->first();
            $available = $wi ? (float) $wi->quantity : 0.0;
            $needed = (float) $item['quantity'];

            if ($needed > $available) {
                $insufficient[] = [
                    'item_id' => (int) $item['itemId'],
                    'code' => (string) $item['code'],
                    'available' => $available,
                    'needed' => $needed,
                ];
            }
        }

        if ($insufficient === []) {
            return null;
        }

        $summary = collect($insufficient)
            ->map(fn (array $row) => "{$row['code']} (avail: {$row['available']}, need: {$row['needed']})")
            ->implode(', ');

        return [
            'message' => 'Stok tidak cukup di gudang '.$warehouse->name.': '.$summary,
            'items' => $insufficient,
        ];
    }

    /**
     * @return array{status: string, message: string, transaction_id?: int}
     */
    protected function createTransaction(int $type, object $dataJubelio): array
    {
        try {
            return DB::transaction(function () use ($type, $dataJubelio) {
                $customer = Addrbook::find($dataJubelio->customer);
                $warehouse = Addrbook::find($dataJubelio->warehouse);

                if (! $customer || ! $warehouse) {
                    throw new \Exception('Customer or Warehouse not found.');
                }

                $this->locationAccessService->ensureJubelioPartyLocations($warehouse, $customer);

                $transaction = new Transaction;
                $transaction->date = Carbon::parse($dataJubelio->date);
                $transaction->type = $type;
                $transaction->adjustment = $dataJubelio->adjustment;
                $transaction->user_id = $dataJubelio->userId ?? Transaction::resolveJubelioCronUserId();
                $transaction->submit_type = Transaction::SUBMIT_TYPE_JUBELIO;
                $transaction->description = $dataJubelio->description ?? '';
                $transaction->notes = $dataJubelio->note ?? '';
                $transaction->invoice = $dataJubelio->invoice;
                $transaction->due = null;
                $transaction->status = Transaction::STATUS_COMPLETED;

                if ($type === Transaction::TYPE_RETURN) {
                    $transaction->sender_id = $customer->id;
                    $transaction->sender_type = $customer->type;
                    $transaction->receiver_id = $warehouse->id;
                    $transaction->receiver_type = $warehouse->type;
                } else {
                    $transaction->sender_id = $warehouse->id;
                    $transaction->sender_type = $warehouse->type;
                    $transaction->receiver_id = $customer->id;
                    $transaction->receiver_type = $customer->type;
                }

                $transaction->save();

                $totalQty = 0;
                $subTotal = 0;

                foreach ($dataJubelio->addMoreInputFields as $item) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'date' => $transaction->date,
                        'transaction_type' => $type,
                        'sender_id' => $transaction->sender_id,
                        'receiver_id' => $transaction->receiver_id,
                        'item_id' => $item['itemId'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => 0,
                        'total' => $item['subtotal'],
                    ]);
                    $totalQty += $item['quantity'];
                    $subTotal += $item['subtotal'];
                }

                $grandTotal = $subTotal + (float) $transaction->adjustment;
                // Match manual Aria / L10: real_total = gross subtotal, total = net receivable.
                $transaction->real_total = Transaction::signedAmount($type, $subTotal);
                $transaction->total = Transaction::signedAmount($type, $grandTotal);
                $transaction->total_items = $totalQty;
                $transaction->save();

                $this->transactionService->handleTransaction($transaction);

                return [
                    'status' => '200',
                    'message' => 'ok',
                    'transaction_id' => $transaction->id,
                ];
            });
        } catch (\Exception $e) {
            return [
                'status' => '422',
                'message' => $e->getMessage(),
            ];
        }
    }
}
