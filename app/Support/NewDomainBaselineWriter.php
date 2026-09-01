<?php

namespace App\Support;

use App\Models\Addrbook;
use App\Models\AddrbookStat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NewDomainBaselineWriter
{
    public static function ensureLocationId(): int
    {
        $locationId = DB::table('locations')->orderBy('id')->value('id');
        if ($locationId) {
            return (int) $locationId;
        }

        $row = [
            'name' => 'Default Location',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('locations', 'child_ids')) {
            $row['child_ids'] = '';
        }
        if (Schema::hasColumn('locations', 'parent_ids')) {
            $row['parent_ids'] = '';
        }

        return (int) DB::table('locations')->insertGetId($row);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function ensureAddrbook(string $name, int $type, array $attributes = []): Addrbook
    {
        $addrbook = Addrbook::query()->firstOrCreate(
            ['name' => $name, 'type' => $type],
            $attributes,
        );

        if (! $addrbook->wasRecentlyCreated && $attributes !== []) {
            $updates = [];
            foreach (['description', 'ledger_hint'] as $key) {
                if (array_key_exists($key, $attributes) && blank($addrbook->{$key}) && filled($attributes[$key])) {
                    $updates[$key] = $attributes[$key];
                }
            }
            if (isset($attributes['operation_id']) && (int) $addrbook->parent_id === 0) {
                $updates['operation_id'] = $attributes['operation_id'];
            }
            if ($updates !== []) {
                $addrbook->update($updates);
            }
        }

        AddrbookStat::query()->firstOrCreate(
            ['customer_id' => $addrbook->id],
            ['balance' => 0],
        );

        $addrbook->locations()->syncWithoutDetaching([self::ensureLocationId()]);

        return $addrbook->fresh() ?? $addrbook;
    }
}
