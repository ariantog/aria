<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Support\ItemCatalog;
use Illuminate\Console\Command;

/**
 * One-off migration helper: copy item_group.description / description2 onto SKUs
 * when the item column is still empty. Does not clear colorway text or touch
 * rows that already have per-SKU overrides.
 */
class BackfillSkuDescriptionsFromColorway extends Command
{
    protected $signature = 'app:backfill-sku-descriptions-from-colorway
                            {--execute : Write changes (default is dry-run)}
                            {--group-id= : Limit to one item_group.id}
                            {--chunk=500 : Items per batch}';

    protected $description = 'Copy colorway description/NB onto SKUs when items.description or items.description2 is empty';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $groupId = $this->option('group-id');
        $chunk = max(1, (int) $this->option('chunk'));

        if (! $execute) {
            $this->warn('Dry run — no rows will be updated. Pass --execute to persist.');
        }

        if (! ItemCatalog::itemColumnExists('description') && ! ItemCatalog::itemColumnExists('description2')) {
            $this->error('items.description columns are not available.');

            return self::FAILURE;
        }

        $itemsTouched = 0;
        $fieldsWritten = 0;

        $query = Item::query()
            ->where('group_id', '>', 0)
            ->with('group')
            ->orderBy('id');

        if ($groupId !== null && $groupId !== '') {
            $query->where('group_id', (int) $groupId);
        }

        $query->chunkById($chunk, function ($items) use ($execute, &$itemsTouched, &$fieldsWritten): void {
            foreach ($items as $item) {
                $group = $item->group;
                if ($group === null) {
                    continue;
                }

                $updates = $this->pendingCopies($item, $group);
                if ($updates === []) {
                    continue;
                }

                $itemsTouched++;
                $fieldsWritten += count($updates);

                $this->line(sprintf(
                    'Item #%d (%s): %s',
                    $item->id,
                    $item->code,
                    collect($updates)->map(fn ($value, $field) => "{$field} ← {$value}")->implode(', '),
                ));

                if ($execute) {
                    foreach ($updates as $field => $value) {
                        $item->{$field} = $value;
                    }
                    $item->save();
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '%s %d item row(s), %d field(s) %s.',
            $execute ? 'Updated' : 'Would update',
            $itemsTouched,
            $fieldsWritten,
            $execute ? 'written' : 'to write',
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function pendingCopies(Item $item, \App\Models\ItemGroup $group): array
    {
        $updates = [];

        if (ItemCatalog::itemColumnExists('description')) {
            $itemText = trim((string) ($item->description ?? ''));
            $groupText = trim((string) ($group->description ?? ''));
            if ($itemText === '' && $groupText !== '') {
                $updates['description'] = strtoupper($groupText);
            }
        }

        if (ItemCatalog::itemColumnExists('description2')) {
            $itemText = trim((string) ($item->description2 ?? ''));
            $groupText = trim((string) ($group->description2 ?? ''));
            if ($itemText === '' && $groupText !== '') {
                $updates['description2'] = strtoupper($groupText);
            }
        }

        return $updates;
    }
}
