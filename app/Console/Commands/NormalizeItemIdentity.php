<?php

namespace App\Console\Commands;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Tag;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class NormalizeItemIdentity extends Command
{
    protected $signature = 'items:normalize-identity {--dry-run : Report changes without writing}';

    protected $description = 'Normalize item group product names and regenerate item display names';

    public function handle(ItemIdentityBuilder $identityBuilder): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $groupsUpdated = 0;
        $itemsUpdated = 0;

        ItemGroup::query()
            ->with(['items.tags'])
            ->orderBy('id')
            ->chunkById(100, function ($groups) use ($identityBuilder, $dryRun, &$groupsUpdated, &$itemsUpdated) {
                foreach ($groups as $group) {
                    $sampleItem = $group->items->first();

                    if (! $sampleItem) {
                        continue;
                    }

                    $pcode = strtoupper(trim((string) $sampleItem->pcode));
                    $currentName = strtoupper(trim((string) $group->name));
                    $targetName = $currentName;

                    if ($sampleItem->type === ItemType::ITEM) {
                        if ($currentName === '' && $pcode !== '') {
                            $targetName = $pcode;
                        } elseif (
                            Schema::hasColumn('item_groups', 'alias')
                            && trim((string) $group->alias) !== ''
                            && ($currentName === '' || $currentName === strtoupper(trim((string) $group->alias)))
                        ) {
                            $aliasName = strtoupper(trim((string) $group->alias));

                            if ($aliasName !== $pcode) {
                                $targetName = $aliasName;
                            }
                        }
                    }

                    if ($targetName !== $currentName) {
                        $groupsUpdated++;
                        $this->line("Group #{$group->id}: name {$currentName} → {$targetName}");

                        if (! $dryRun) {
                            $group->name = $targetName;
                            $group->save();
                        }
                    }

                    $groupName = $dryRun ? $targetName : $group->name;

                    foreach ($group->items as $item) {
                        $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
                        $sizeTag = $item->tags->firstWhere('type', Tag::TYPE_SIZE);
                        $expectedName = $identityBuilder->buildName($groupName, $warnaTag, $sizeTag);

                        if ($item->name !== $expectedName) {
                            $itemsUpdated++;
                            $this->line("  Item #{$item->id} ({$item->code}): {$item->name} → {$expectedName}");

                            if (! $dryRun) {
                                $item->name = $expectedName;
                                $item->save();
                            }
                        }
                    }
                }
            });

        $this->newLine();
        $this->info("Groups updated: {$groupsUpdated}");
        $this->info("Items updated: {$itemsUpdated}");

        if ($dryRun) {
            $this->warn('Re-run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }
}
