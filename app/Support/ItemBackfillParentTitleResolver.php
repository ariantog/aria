<?php

namespace App\Support;

use App\Models\ItemGroup;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve the marketing parent title for backfill commands (product_name → group name → group alias).
 */
final class ItemBackfillParentTitleResolver
{
    /**
     * @return array{
     *     title: string|null,
     *     source: 'product_name'|'group_name'|'group_alias'|'skip_product_pcode'|'none',
     *     detail?: string
     * }
     */
    public static function resolve(string $parentKey, ItemGroup $group): array
    {
        ItemProductTitle::forgetParentProductNameCache($parentKey);
        $productName = ItemProductTitle::parentProductNameForKey($parentKey);

        if ($productName !== '' && ItemParentTitleEligibility::looksLikePcodeOrSegment($productName)) {
            return [
                'title' => null,
                'source' => 'skip_product_pcode',
                'detail' => $productName,
            ];
        }

        if ($productName !== '') {
            return [
                'title' => strtoupper(trim($productName)),
                'source' => 'product_name',
            ];
        }

        $fromName = ItemGroupMarketingTitleFallback::fromGroupName((string) ($group->name ?? ''));
        if ($fromName !== null) {
            return [
                'title' => $fromName,
                'source' => 'group_name',
            ];
        }

        if (Schema::hasColumn($group->getTable(), 'alias')) {
            $rawAlias = (string) ($group->getAttributes()['alias'] ?? '');
            $fromAlias = ItemGroupAliasMarketingTitle::extract($rawAlias);
            if ($fromAlias !== null) {
                return [
                    'title' => $fromAlias,
                    'source' => 'group_alias',
                ];
            }
        }

        return [
            'title' => null,
            'source' => 'none',
        ];
    }
}
