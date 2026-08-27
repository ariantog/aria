<?php

namespace App\Services\Tax;

use App\Models\Item;
use Illuminate\Support\Collection;

class FakturLineItemMatcher
{
    /**
     * Propose inventory matches for parsed faktur line items.
     *
     * @param  list<array{line_no?: int, name?: string, unit_price?: float, quantity?: float, total?: float}>  $lineItems
     * @return list<array{
     *     line_no: int,
     *     name: string,
     *     quantity: float,
     *     unit_price: float,
     *     total: float,
     *     matches: list<array{id: int, name: string, code: string|null, pcode: string|null, score: int}>,
     *     best_match: array{id: int, name: string, code: string|null, pcode: string|null, score: int}|null,
     * }>
     */
    public function propose(array $lineItems): array
    {
        return collect($lineItems)
            ->map(function (array $line, int $index) {
                $lineNo = (int) ($line['line_no'] ?? ($index + 1));
                $name = trim((string) ($line['name'] ?? ''));
                $quantity = (float) ($line['quantity'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $total = (float) ($line['total'] ?? 0);
                $matches = $this->matchName($name);

                return [
                    'line_no' => $lineNo,
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                    'matches' => $matches->values()->all(),
                    'best_match' => $matches->first(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string, code: string|null, pcode: string|null, score: int}>
     */
    private function matchName(string $name): Collection
    {
        if ($name === '') {
            return collect();
        }

        $normalized = $this->normalize($name);
        $candidates = collect();

        $exact = Item::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->limit(5)
            ->get(['id', 'name', 'code', 'pcode']);

        foreach ($exact as $item) {
            $candidates->push([
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'pcode' => $item->pcode,
                'score' => 100,
            ]);
        }

        $token = $this->extractLeadingCode($name);
        if ($token !== null) {
            $byCode = Item::query()
                ->where(function ($query) use ($token) {
                    $query->where('code', $token)
                        ->orWhere('pcode', $token);
                })
                ->limit(5)
                ->get(['id', 'name', 'code', 'pcode']);

            foreach ($byCode as $item) {
                $candidates->push([
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'pcode' => $item->pcode,
                    'score' => 80,
                ]);
            }
        }

        $like = Item::query()
            ->where(function ($query) use ($name, $normalized) {
                $query->where('name', 'like', '%'.$name.'%')
                    ->orWhere('name', 'like', '%'.$normalized.'%');
            })
            ->limit(10)
            ->get(['id', 'name', 'code', 'pcode']);

        foreach ($like as $item) {
            $candidates->push([
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'pcode' => $item->pcode,
                'score' => 50,
            ]);
        }

        return $candidates
            ->unique('id')
            ->sortByDesc('score')
            ->values()
            ->take(5);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    private function extractLeadingCode(string $name): ?string
    {
        if (preg_match('/^(\d{4,})\s+/', $name, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
