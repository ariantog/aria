<?php

namespace App\Actions\Transactions;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class PrepareMoveTransactionDraft
{
    /**
     * @param  list<array{item_id: int, quantity: float, from_warehouse_id: int, to_warehouse_id: int}>  $items
     */
    public function execute(array $items, bool $rememberArrangementDraft = false): RedirectResponse
    {
        if ($items === []) {
            return back()->with('error', 'No items selected for the move draft.');
        }

        $fromId = (int) $items[0]['from_warehouse_id'];
        $toId = (int) $items[0]['to_warehouse_id'];

        foreach ($items as $row) {
            if ((int) $row['from_warehouse_id'] !== $fromId || (int) $row['to_warehouse_id'] !== $toId) {
                return back()->with('error', 'All items in one draft must share the same source and destination warehouses.');
            }
        }

        $draftItemIds = collect($items)->pluck('item_id')->map(fn ($id) => (int) $id);
        if ($draftItemIds->unique()->count() !== $draftItemIds->count()) {
            return back()->with('error', 'Each SKU can only be selected once.');
        }

        $from = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->findOrFail($fromId);

        $to = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->where('arrangement_enabled', true)
            ->findOrFail($toId);

        $itemIds = $draftItemIds->all();
        $loadedItems = Item::query()
            ->with(['warehouseItems', 'group', 'tags'])
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $prefillItems = [];
        foreach ($items as $row) {
            $item = $loadedItems->get((int) $row['item_id']);
            if (! $item) {
                continue;
            }

            $quantity = (float) $row['quantity'];
            $price = $item->effectivePrice();

            $prefillItems[] = [
                'item_id' => (string) $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'quantity' => $quantity,
                'price' => $price,
                'discount' => 0,
                'subtotal' => $quantity * $price,
                'warehouse_item' => $item->warehouseItems->map(fn ($wi) => [
                    'warehouse_id' => (string) $wi->warehouse_id,
                    'quantity' => (float) $wi->quantity,
                ])->values()->all(),
            ];
        }

        if ($prefillItems === []) {
            return back()->with('error', 'No valid items selected for the move draft.');
        }

        Session::put('transaction_move_prefill', [
            'sender_id' => (string) $from->id,
            'sender' => ['id' => $from->id, 'name' => $from->name],
            'receiver_id' => (string) $to->id,
            'receiver' => ['id' => $to->id, 'name' => $to->name],
            'items' => $prefillItems,
        ]);

        if ($rememberArrangementDraft) {
            $sessionKey = 'warehouse_arrangement_drafted';
            $all = session($sessionKey, []);
            $existing = array_map('intval', $all[$toId] ?? []);
            $merged = array_values(array_unique(array_merge($existing, array_map('intval', $itemIds))));
            $all[$toId] = $merged;
            session([$sessionKey => $all]);
        }

        return redirect()->route('transactions.create', ['type' => 'move']);
    }
}
