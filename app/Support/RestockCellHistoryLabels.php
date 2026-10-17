<?php

namespace App\Support;

final class RestockCellHistoryLabels
{
    public const ACTOR_USER = 'user';

    public const ACTOR_CRON = 'cron';

    public const ACTOR_SYSTEM = 'system';

    public const SOURCE_SHEET_SAVE = 'sheet_save';

    public const SOURCE_SHEET_MOVE = 'sheet_move';

    public const SOURCE_FLAT_IMPORT = 'flat_import';

    public const SOURCE_SHEET_RECEIVE = 'sheet_receive';

    public const SOURCE_MISSING_FOUND = 'missing_found';

    public const SOURCE_RECOMMENDATIONS_APPLY = 'recommendations_apply';

    public const SOURCE_RECOMMENDATIONS_SAVE = 'recommendations_save';

    public const SOURCE_SYNC_SKUS = 'sync_skus';

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'edit' => 'Manual edit',
            'move' => 'Pipeline move',
            'receive' => 'Receive to warehouse',
            'missing' => 'Marked missing',
            'found' => 'Missing cleared',
            'sync_remove' => 'SKU removed (sync)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_SHEET_SAVE => 'Restock sheet save',
            self::SOURCE_SHEET_MOVE => 'Restock sheet move',
            self::SOURCE_FLAT_IMPORT => 'Flat Excel import',
            self::SOURCE_SHEET_RECEIVE => 'Restock receive',
            self::SOURCE_MISSING_FOUND => 'Missing SKU page',
            self::SOURCE_RECOMMENDATIONS_APPLY => 'Recommendations (apply)',
            self::SOURCE_RECOMMENDATIONS_SAVE => 'Recommendations (save)',
            self::SOURCE_SYNC_SKUS => 'Sync SKUs',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actorLabels(): array
    {
        return [
            self::ACTOR_USER => 'User',
            self::ACTOR_CRON => 'Scheduled job',
            self::ACTOR_SYSTEM => 'System',
        ];
    }

    public static function actionLabel(string $action): string
    {
        return self::actionLabels()[$action] ?? $action;
    }

    public static function sourceLabel(?string $source): string
    {
        if ($source === null || $source === '') {
            return '—';
        }

        return self::sourceLabels()[$source] ?? $source;
    }

    public static function actorLabel(string $actorType): string
    {
        return self::actorLabels()[$actorType] ?? $actorType;
    }

    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'restock' => 'Restock',
            'production' => 'Production',
            'shipped' => 'Shipped',
            'missing' => 'Missing',
            default => $field,
        };
    }
}
