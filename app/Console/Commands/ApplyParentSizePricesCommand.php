<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemParent;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemPricing;
use Illuminate\Console\Command;

class ApplyParentSizePricesCommand extends Command
{
    protected $signature = 'app:apply-parent-size-prices
        {parent : item_parents.id (e.g. 67 for items-group/parent/67)}
        {--map= : Comma-separated SIZE:amount pairs, e.g. LIGHT:49000,MEDIUM:59000,XHEAVY:79000}
        {--field=price : Pricing field (price, reseller_price, cost, cost_cnh)}
        {--dry-run : List changes without writing}
        {--no-clear-parent-price : Keep item_parents default price after updates (default: clear parent price to 0)}
        {--no-preserve-unmapped : Skip SKUs whose size tag is not listed in --map}';

    protected $description = 'Set per-SKU pricing under an item parent from size tag codes (repair mistaken parent-wide overrides)';

    public function handle(
        ItemGroupHierarchyService $hierarchy,
        ItemIdentityBuilder $identityBuilder,
    ): int {
        $parentId = (int) $this->argument('parent');
        if ($parentId <= 0) {
            $this->error('Parent id must be a positive integer.');

            return self::FAILURE;
        }

        $mapRaw = trim((string) $this->option('map'));
        if ($mapRaw === '') {
            $this->error('Provide --map=SIZE:amount,... (e.g. --map=LIGHT:49000,MEDIUM:59000,XHEAVY:79000)');

            return self::FAILURE;
        }

        $field = (string) $this->option('field');
        if (! in_array($field, ItemPricing::FIELDS, true)) {
            $this->error('Invalid --field. Allowed: '.implode(', ', ItemPricing::FIELDS));

            return self::FAILURE;
        }

        /** @var array<string, float> $map */
        $map = $this->parseMap($mapRaw);
        if ($map === []) {
            $this->error('Could not parse --map. Use SIZE:amount pairs separated by commas.');

            return self::FAILURE;
        }

        $parent = ItemParent::query()->find($parentId);
        if ($parent === null) {
            $this->error("No item_parents row for id {$parentId}.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $clearParent = ! (bool) $this->option('no-clear-parent-price');
        $preserveUnmapped = ! (bool) $this->option('no-preserve-unmapped');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $groups = $hierarchy->groupsForParentId($parentId);
        if ($groups->isEmpty()) {
            $this->warn("No colorway groups found for parent #{$parentId}.");

            return self::SUCCESS;
        }

        /** @var \Illuminate\Support\Collection<int, Item> $items */
        $items = $groups->flatMap(fn ($group) => $group->items)->unique('id')->values();

        if ($items->isEmpty()) {
            $this->warn('No active SKUs under this parent.');

            return self::SUCCESS;
        }

        $rows = [];
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $item->loadMissing(['tags', 'group.parent']);
            $sizeCode = $identityBuilder->itemSizeCode($item);
            $oldEffective = ItemPricing::resolve($item, $field);

            if ($sizeCode === null || $sizeCode === '') {
                $rows[] = [
                    (string) $item->id,
                    (string) $item->code,
                    '—',
                    $this->formatMoney($oldEffective),
                    '—',
                    'skip (no size)',
                ];
                $skipped++;

                continue;
            }

            $target = null;
            $reason = '';

            if (array_key_exists($sizeCode, $map)) {
                $target = $map[$sizeCode];
                $reason = 'map';
            } elseif ($preserveUnmapped) {
                $target = $oldEffective;
                $reason = 'preserve effective';
            } else {
                $rows[] = [
                    (string) $item->id,
                    (string) $item->code,
                    $sizeCode,
                    $this->formatMoney($oldEffective),
                    '—',
                    'skip (unmapped)',
                ];
                $skipped++;

                continue;
            }

            $rows[] = [
                (string) $item->id,
                (string) $item->code,
                $sizeCode,
                $this->formatMoney($oldEffective),
                $this->formatMoney($target),
                $reason,
            ];

            $inheritsParent = ItemPricing::detectScope($item, $field) !== ItemPricing::SCOPE_SIZE;
            $shouldWrite = abs($oldEffective - $target) > 0.001 || ($clearParent && $inheritsParent);

            if ($shouldWrite) {
                if ($dryRun) {
                    $updated++;
                } else {
                    ItemPricing::apply($item, $field, ItemPricing::SCOPE_SIZE, $target);
                    $updated++;
                }
            }
        }

        $this->table(
            ['item_id', 'code', 'size', 'effective_before', 'target', 'note'],
            $rows,
        );

        if (! $dryRun && $clearParent) {
            ItemPricing::applyForParent($parentId, [
                $field => [
                    'scope' => ItemPricing::SCOPE_GROUP,
                    'value' => 0,
                ],
            ], cascadeOverrides: false);
            $this->info("Cleared parent #{$parentId} {$field} default (set to 0).");
        }

        ItemPricing::flushRequestCache();

        $verb = $dryRun ? 'to update' : 'updated';
        $this->info("Parent #{$parentId}: {$items->count()} SKU(s), {$updated} {$verb}, {$skipped} skipped.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, float>
     */
    private function parseMap(string $mapRaw): array
    {
        $out = [];

        foreach (explode(',', $mapRaw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            $segments = explode(':', $pair, 2);
            if (count($segments) !== 2) {
                continue;
            }

            [$size, $amount] = $segments;
            $size = strtoupper(trim($size));
            if ($size === '') {
                continue;
            }

            $out[$size] = max(0, (float) trim($amount));
        }

        return $out;
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 0, '.', ',');
    }
}
