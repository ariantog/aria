<?php

namespace App\Services;

use App\Models\User;
use App\Support\SidebarFavoriteRegistry;
use App\Support\UserPreferenceRegistry;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class SidebarFavoriteService
{
    public function __construct(
        protected UserPreferenceService $preferences,
    ) {}

    /**
     * @return list<string>
     */
    public function favoriteKeys(User $user): array
    {
        $value = $this->preferences->get($user, UserPreferenceRegistry::FAVORITES_SLUG, []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            fn ($key) => is_string($key) && $key !== '',
        ));
    }

    /**
     * @param  list<string>  $keys
     */
    public function updateFavoriteKeys(User $user, array $keys): void
    {
        $keys = array_values(array_unique(array_filter(
            $keys,
            fn ($key) => is_string($key) && $key !== '',
        )));

        if (count($keys) > UserPreferenceRegistry::FAVORITES_MAX) {
            throw new InvalidArgumentException('You can save at most '.UserPreferenceRegistry::FAVORITES_MAX.' favorite links.');
        }

        $registry = SidebarFavoriteRegistry::keyed();

        foreach ($keys as $key) {
            if (! isset($registry[$key])) {
                throw new InvalidArgumentException('One or more favorite links are invalid.');
            }

            if (! $this->userCanAccessLink($user, $registry[$key])) {
                throw new InvalidArgumentException('One or more favorite links are not available for your account.');
            }
        }

        $this->preferences->set($user, UserPreferenceRegistry::FAVORITES_SLUG, $keys === [] ? null : $keys);
    }

    /**
     * Registry entries the user may pick in settings.
     *
     * @return list<array{key: string, label: string, group: string}>
     */
    public function availableLinks(User $user): array
    {
        return collect(SidebarFavoriteRegistry::links())
            ->filter(fn (array $link) => $this->userCanAccessLink($user, $link))
            ->map(fn (array $link) => [
                'key' => $link['key'],
                'label' => $link['label'],
                'group' => $link['group'],
            ])
            ->values()
            ->all();
    }

    /**
     * Saved favorites resolved for sidebar display, filtered by current permissions.
     *
     * @return list<array{key: string, label: string, url: string, active_prefix: string}>
     */
    public function resolvedFavorites(User $user): array
    {
        $registry = SidebarFavoriteRegistry::keyed();
        $resolved = [];

        foreach ($this->favoriteKeys($user) as $key) {
            $link = $registry[$key] ?? null;

            if (! $link || ! $this->userCanAccessLink($user, $link)) {
                continue;
            }

            if (! Route::has($link['route'])) {
                continue;
            }

            $resolved[] = [
                'key' => $link['key'],
                'label' => $link['label'],
                'url' => route($link['route'], $link['params']),
                'active_prefix' => $link['active_prefix'],
            ];
        }

        return $resolved;
    }

    /**
     * @param  array{permission: ?string, superadmin_only?: bool}  $link
     */
    public function userCanAccessLink(User $user, array $link): bool
    {
        if (($link['superadmin_only'] ?? false) && ! User::isSuperadmin($user)) {
            return false;
        }

        if ($link['permission'] === null) {
            return ! ($link['superadmin_only'] ?? false) || User::isSuperadmin($user);
        }

        if (User::isSuperadmin($user)) {
            return true;
        }

        return $user->can($link['permission']);
    }
}
