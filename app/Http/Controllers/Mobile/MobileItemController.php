<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileItemController extends Controller
{
    public function byCode(Request $request): JsonResponse
    {
        Transaction::authorizeTypeAccess('sell');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $item = Item::findBySkuOrName($validated['code']);
        if ($item) {
            $item->loadMissing('warehouseItems', 'group', 'tags');
        }

        return response()->json([
            'item' => $item ? $this->payload($item) : null,
        ]);
    }

    public function byId(Request $request): JsonResponse
    {
        Transaction::authorizeTypeAccess('sell');

        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $item = Item::with(['warehouseItems', 'group', 'tags'])->find($validated['id']);

        return response()->json([
            'item' => $item ? $this->payload($item) : null,
        ]);
    }

    public function barcodeLookup(Request $request): JsonResponse
    {
        Transaction::authorizeTypeAccess('sell');

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ]);

        $trim = trim($validated['barcode']);
        if ($trim === '') {
            return response()->json(['item' => null]);
        }

        if (ctype_digit($trim)) {
            $item = Item::with(['warehouseItems', 'group', 'tags'])->find((int) $trim);
            if ($item) {
                return response()->json(['item' => $this->payload($item)]);
            }
        }

        $item = Item::findBySkuOrName($trim);
        if ($item) {
            $item->loadMissing('warehouseItems', 'group', 'tags');
        }

        return response()->json([
            'item' => $item ? $this->payload($item) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Item $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->getItemCode(),
            'name' => $item->effectiveDisplayName(),
            'type' => $item->type?->value,
            'price' => $item->effectivePrice(),
            'reseller_sell_price' => $item->resellerSellPrice(),
            'cost' => $item->effectiveCost(),
            'jubelio_item_id' => (int) ($item->jubelio_item_id ?? 0),
            'track_inventory' => $item->tracksInventory() && (bool) ($item->track_inventory ?? true),
            'allow_decimal_quantity' => $item->allowsDecimalQuantity(),
            'warehouse_item' => $item->warehouseItems->map(fn ($wi) => [
                'warehouse_id' => (string) $wi->warehouse_id,
                'quantity' => (float) $wi->quantity,
            ])->values()->all(),
        ];
    }
}
