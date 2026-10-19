<?php

namespace App\Services\Lark;

use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Support\Collection;

class LarkRosterService
{
    /**
     * @param  list<string>  $roleSlugs
     * @return list<string> Lark open_ids
     */
    public function openIdsForRoles(array $roleSlugs): array
    {
        if ($roleSlugs === []) {
            return [];
        }

        $roleIds = StaffRole::query()
            ->whereIn('slug', $roleSlugs)
            ->where('is_active', true)
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->where('active', true)
            ->whereKeyNot(User::SUPERADMIN_ID)
            ->whereNotNull('lark_open_id')
            ->where('lark_open_id', '!=', '')
            ->whereHas('staffRoles', fn ($q) => $q->whereIn('staff_roles.id', $roleIds))
            ->pluck('lark_open_id')
            ->unique()
            ->values()
            ->all();
    }

    public function mentionLineForRoles(array $roleSlugs): ?string
    {
        $openIds = $this->openIdsForRoles($roleSlugs);
        if ($openIds === []) {
            return null;
        }

        return implode(' ', array_map(fn (string $id) => '<at id="'.$id.'"></at>', $openIds));
    }

    public function findUserByOpenId(?string $openId): ?User
    {
        if ($openId === null || trim($openId) === '') {
            return null;
        }

        return User::query()
            ->where('lark_open_id', $openId)
            ->where('active', true)
            ->first();
    }
}
