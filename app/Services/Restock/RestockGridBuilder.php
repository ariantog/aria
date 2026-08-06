<?php

namespace App\Services\Restock;

use App\Models\RestockCell;
use App\Models\RestockCellHistory;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RestockGridBuilder
{
  private const SIZE_ORDER = ['S', 'M', 'L', 'XL', 'XXL'];

  /**
   * @return array{sheet_id: int, parents: list<array{pcode: string, name: string, sizes: list<string>, rows: list<array<string, mixed>>}>}
   */
  public function build(RestockSheet $sheet): array
  {
    $sheet->loadMissing([
      'cells.color',
      'cells.size',
      'cells.item.group',
      'cells.item.warehouseItems',
    ]);

    $parents = $this->cellsGroupedByParent($sheet)
      ->map(function (Collection $cells, string $pcode) {
        $sizes = $this->orderedSizeCodes($cells);
        $rows = $this->buildColorRows($cells, $sizes);

        return [
          'pcode' => $pcode,
          'name' => $cells->first()?->item?->group?->name ?? $pcode,
          'sizes' => $sizes,
          'rows' => $rows,
        ];
      })
      ->values()
      ->all();

    return [
      'sheet_id' => $sheet->id,
      'parents' => $parents,
    ];
  }

  /**
   * @return Collection<string, Collection<int, RestockCell>>
   */
  protected function cellsGroupedByParent(RestockSheet $sheet): Collection
  {
    return $sheet->cells
      ->sortBy([
        fn (RestockCell $cell) => $cell->item?->pcode ?? '',
        fn (RestockCell $cell) => $cell->color?->name ?? '',
        fn (RestockCell $cell) => $cell->size?->name ?? '',
      ])
      ->groupBy(fn (RestockCell $cell) => $cell->item?->pcode ?? 'unknown');
  }

  /**
   * @param  Collection<int, RestockCell>  $cells
   * @return list<string>
   */
  protected function orderedSizeCodes(Collection $cells): array
  {
    $codes = $cells
      ->map(fn (RestockCell $cell) => $cell->size?->code)
      ->filter()
      ->unique()
      ->values();

    if ($codes->isEmpty()) {
      return ['—'];
    }

    return $codes
      ->sortBy(fn (string $code) => $this->sizeSortKey($code))
      ->values()
      ->all();
  }

  /**
   * @param  Collection<int, RestockCell>  $cells
   * @param  list<string>  $sizes
   * @return list<array<string, mixed>>
   */
  protected function buildColorRows(Collection $cells, array $sizes): array
  {
    return $cells
      ->groupBy(fn (RestockCell $cell) => $cell->color_id ?? 0)
      ->map(function (Collection $colorCells) use ($sizes) {
        $first = $colorCells->first();
        $row = [
          'color_id' => $first->color_id,
          'color_name' => $first->color?->name ?? '—',
          'is_urgent' => $colorCells->contains(fn (RestockCell $c) => $c->is_urgent),
          '_meta' => [],
        ];

        foreach ($sizes as $sizeCode) {
          $cell = $colorCells->first(function (RestockCell $c) use ($sizeCode) {
            if ($sizeCode === '—') {
              return $c->size_id === null;
            }

            return strtoupper($c->size?->code ?? '') === strtoupper($sizeCode);
          });

          if (! $cell) {
            continue;
          }

          $stock = (int) $cell->item?->warehouseItems?->sum('quantity') ?? 0;
          $prefix = $this->fieldPrefix($sizeCode);

          $row["{$prefix}restock"] = $cell->qty_restock;
          $row["{$prefix}production"] = $cell->qty_production;
          $row["{$prefix}shipped"] = $cell->qty_shipped;
          $row["{$prefix}stock"] = $stock;
          $row['_meta'][$prefix] = [
            'cell_id' => $cell->id,
            'is_urgent' => $cell->is_urgent,
          ];
        }

        return $row;
      })
      ->values()
      ->all();
  }

  protected function fieldPrefix(string $sizeCode): string
  {
    if ($sizeCode === '—') {
      return '';
    }

    return str_replace(['.', ' '], '_', strtolower($sizeCode)).'_';
  }

  protected function sizeSortKey(string $code): string
  {
    $upper = strtoupper($code);
    $index = array_search($upper, self::SIZE_ORDER, true);

    if ($index !== false) {
      return '0_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'_'.$upper;
    }

    return '1_'.$upper;
  }
}
