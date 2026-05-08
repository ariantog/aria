<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Restock;
use App\Models\RestockHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class RestockImport implements ToCollection
{
    public $errors = [];

    public function __construct(public string $date, public string $type) {}

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $itemCode = $row[0];
            $qty = (int) $row[1];

            if (! $itemCode || $qty <= 0) {
                continue;
            }

            $item = Item::where('id', $itemCode)->orWhere('code', $itemCode)->first();

            if (! $item) {
                $this->errors[] = "Item with code/ID {$itemCode} not found.";

                continue;
            }

            try {
                DB::transaction(function () use ($item, $qty) {
                    $restock = Restock::where('item_id', $item->id)->lockForUpdate()->first();

                    $before = 0;
                    $after = 0;

                    if ($restock) {
                        $before = $this->getQtyByType($restock, $this->type);

                        // Follow controller logic for transitions
                        if ($this->type === 'production') {
                            if ($restock->restocked_quantity < $qty) {
                                throw new \Exception("Restocked quantity not enough for item {$item->code}");
                            }
                            $restock->restocked_quantity -= $qty;
                            $restock->in_production_quantity += $qty;
                            $after = $restock->in_production_quantity;
                        } elseif ($this->type === 'shipped') {
                            if ($restock->in_production_quantity < $qty) {
                                throw new \Exception("Production quantity not enough for item {$item->code}");
                            }
                            $restock->in_production_quantity -= $qty;
                            $restock->shipped_quantity += $qty;
                            $after = $restock->shipped_quantity;
                        } else {
                            $this->updateQtyByType($restock, $this->type, $qty);
                            $after = $before + $qty;
                        }

                        $restock->date = $this->date;
                        $restock->save();
                    } else {
                        if ($this->type !== 'restocked') {
                            throw new \Exception("Restock entry for item {$item->code} must exist before updating {$this->type}.");
                        }

                        $restock = Restock::create([
                            'item_id' => $item->id,
                            'date' => $this->date,
                            'status' => 1,
                            'restocked_quantity' => $qty,
                            'in_production_quantity' => 0,
                            'shipped_quantity' => 0,
                            'missing_quantity' => 0,
                        ]);
                        $before = 0;
                        $after = $qty;
                    }

                    RestockHistory::create([
                        'restock_id' => $restock->id,
                        'item_id' => $item->id,
                        'step' => $this->type,
                        'action' => 'import',
                        'qty_before' => $before,
                        'qty_after' => $after,
                        'qty_changed' => $qty,
                        'user_id' => Auth::id(),
                        'date' => $this->date,
                    ]);
                });
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }
    }

    protected function getQtyByType(Restock $restock, string $type): int
    {
        return match ($type) {
            'restocked' => $restock->restocked_quantity,
            'production' => $restock->in_production_quantity,
            'shipped' => $restock->shipped_quantity,
            'missing' => $restock->missing_quantity,
            default => 0,
        };
    }

    protected function updateQtyByType(Restock $restock, string $type, int $qty): void
    {
        switch ($type) {
            case 'restocked':
                $restock->increment('restocked_quantity', $qty);
                break;
            case 'missing':
                $restock->increment('missing_quantity', $qty);
                break;
        }
    }
}
