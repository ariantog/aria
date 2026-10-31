<?php

namespace App\Services\Lark;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StaffRole;

class LarkSettingsService
{
    public const SETTING_SLUG = 'lark_ops_settings';

    /**
     * @return array<string, string>
     */
    public function eventLabels(): array
    {
        return [
            'jubelio.oversell.webhook' => 'Jubelio oversell (webhook scan)',
            'stock.low_cover' => 'Stok rendah (cover)',
            'stock.low_cover.both' => 'Stok rendah — Aria & Jubelio',
            'stock.mismatch' => 'Selisih stok Aria vs Jubelio',
            'jubelio.oversell.blocked' => 'Jubelio oversell (posting blocked)',
            'stock.restock.pending' => 'Restock approval (Lark card)',
        ];
    }

    /**
     * Defaults from config file (env overrides still apply at deploy for secrets / master switch).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'low_cover' => [
                'default_cover_days' => (int) config('lark.low_cover.default_cover_days', 14),
                'best_seller_cover_days' => (int) config('lark.low_cover.best_seller_cover_days', 30),
                'rearm_buffer_days' => (float) config('lark.low_cover.rearm_buffer_days', 4),
                'mismatch_qty_delta' => (int) config('lark.low_cover.mismatch_qty_delta', 3),
                'alert_item_types' => $this->normalizeAlertItemTypes(
                    config('lark.low_cover.alert_item_types', [ItemType::ASSET_LANCAR->value]),
                ),
            ],
            'jubelio_order_url_template' => config('lark.jubelio_order_url_template'),
            'events' => config('lark.events', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Setting::getValue(self::SETTING_SLUG);
        if (! is_array($stored)) {
            return $this->defaults();
        }

        return $this->mergeStored($stored);
    }

    /**
     * @return array{default_cover_days: int, best_seller_cover_days: int, rearm_buffer_days: float, mismatch_qty_delta: int}
     */
    public function lowCover(): array
    {
        $all = $this->all();

        return $all['low_cover'];
    }

    /**
     * @return list<int> ItemType values that receive stok rendah / mismatch-from-scan Lark alerts.
     */
    public function lowCoverAlertItemTypes(): array
    {
        $types = $this->lowCover()['alert_item_types'] ?? [ItemType::ASSET_LANCAR->value];

        return $this->normalizeAlertItemTypes($types);
    }

    public function itemTypeEligibleForLowCoverAlert(Item $item): bool
    {
        $type = ItemType::coerce($item->type);
        if ($type === null || $type === ItemType::SERVICE) {
            return false;
        }

        return in_array($type->value, $this->lowCoverAlertItemTypes(), true);
    }

    /**
     * @return array<int, string> ItemType value => label (Lark Ops UI). Service omitted (unlimited stock).
     */
    public static function lowCoverItemTypeOptions(): array
    {
        return [
            ItemType::ITEM->value => 'Manufaktur (item)',
            ItemType::ASSET_LANCAR->value => 'Asset lancar',
            ItemType::ASSET_TETAP->value => 'Asset tetap',
        ];
    }

    public function jubelioOrderUrlTemplate(): ?string
    {
        $template = $this->all()['jubelio_order_url_template'] ?? null;

        if (! is_string($template) || trim($template) === '') {
            return null;
        }

        return trim($template);
    }

    /**
     * @return array{mention: string, roles: list<string>}
     */
    public function eventConfig(string $eventKey): array
    {
        $events = $this->all()['events'] ?? [];
        $config = $events[$eventKey] ?? null;
        if (! is_array($config)) {
            return ['mention' => 'none', 'roles' => []];
        }

        $mention = ($config['mention'] ?? 'none') === 'roster' ? 'roster' : 'none';
        $roles = array_values(array_filter(
            is_array($config['roles'] ?? null) ? $config['roles'] : [],
            fn ($slug) => is_string($slug) && $slug !== '',
        ));

        return ['mention' => $mention, 'roles' => $roles];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): void
    {
        $defaults = $this->defaults();
        $eventKeys = array_keys($defaults['events'] ?? $this->eventLabels());

        $events = [];
        foreach ($eventKeys as $key) {
            $row = is_array($input['events'][$key] ?? null) ? $input['events'][$key] : [];
            $mention = ($row['mention'] ?? 'none') === 'roster' ? 'roster' : 'none';
            $roleSlugs = [];
            if ($mention === 'roster' && is_array($row['roles'] ?? null)) {
                $allowed = StaffRole::query()->where('is_active', true)->pluck('slug')->all();
                $allowedMap = array_fill_keys($allowed, true);
                foreach ($row['roles'] as $slug) {
                    if (is_string($slug) && isset($allowedMap[$slug])) {
                        $roleSlugs[] = $slug;
                    }
                }
            }
            $events[$key] = ['mention' => $mention, 'roles' => array_values(array_unique($roleSlugs))];
        }

        $template = isset($input['jubelio_order_url_template']) ? trim((string) $input['jubelio_order_url_template']) : '';

        $payload = [
            'low_cover' => [
                'default_cover_days' => (int) ($input['default_cover_days'] ?? $defaults['low_cover']['default_cover_days']),
                'best_seller_cover_days' => (int) ($input['best_seller_cover_days'] ?? $defaults['low_cover']['best_seller_cover_days']),
                'rearm_buffer_days' => (float) ($input['rearm_buffer_days'] ?? $defaults['low_cover']['rearm_buffer_days']),
                'mismatch_qty_delta' => (int) ($input['mismatch_qty_delta'] ?? $defaults['low_cover']['mismatch_qty_delta']),
                'alert_item_types' => $this->normalizeAlertItemTypes(
                    $input['low_cover_alert_item_types'] ?? $defaults['low_cover']['alert_item_types'],
                ),
            ],
            'jubelio_order_url_template' => $template !== '' ? $template : null,
            'events' => $events,
        ];

        Setting::query()->updateOrCreate(
            ['slug' => self::SETTING_SLUG],
            [
                'name' => 'Lark Ops Settings',
                'group' => 'lark',
                'value' => $payload,
                'location_id' => 0,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function mergeStored(array $stored): array
    {
        $defaults = $this->defaults();
        $merged = $defaults;

        if (is_array($stored['low_cover'] ?? null)) {
            $merged['low_cover'] = array_merge($defaults['low_cover'], $stored['low_cover']);
        }
        if (array_key_exists('jubelio_order_url_template', $stored)) {
            $merged['jubelio_order_url_template'] = $stored['jubelio_order_url_template'];
        }

        $defaultEvents = is_array($defaults['events']) ? $defaults['events'] : [];
        $storedEvents = is_array($stored['events'] ?? null) ? $stored['events'] : [];
        $events = [];
        foreach (array_keys($this->eventLabels()) as $key) {
            $base = is_array($defaultEvents[$key] ?? null) ? $defaultEvents[$key] : ['mention' => 'none', 'roles' => []];
            $over = is_array($storedEvents[$key] ?? null) ? $storedEvents[$key] : [];
            $events[$key] = array_merge($base, $over);
        }
        $merged['events'] = $events;

        return $merged;
    }

    /**
     * @param  mixed  $types
     * @return list<int>
     */
    private function normalizeAlertItemTypes(mixed $types): array
    {
        $allowed = array_keys(self::lowCoverItemTypeOptions());
        $allowedMap = array_fill_keys($allowed, true);
        $list = is_array($types) ? $types : [$types];
        $normalized = [];
        foreach ($list as $type) {
            $int = (int) $type;
            if (isset($allowedMap[$int])) {
                $normalized[] = $int;
            }
        }

        if ($normalized === []) {
            return [ItemType::ASSET_LANCAR->value];
        }

        sort($normalized);

        return array_values(array_unique($normalized));
    }
}
