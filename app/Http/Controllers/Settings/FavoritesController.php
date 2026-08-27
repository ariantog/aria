<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\SidebarFavoriteService;
use App\Support\UserPreferenceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class FavoritesController extends Controller
{
    public function __construct(
        protected SidebarFavoriteService $favorites,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $available = collect($this->favorites->availableLinks($user))
            ->groupBy('group')
            ->sortKeys()
            ->all();

        $selected = $this->favorites->favoriteKeys($user);
        $slots = [];

        for ($i = 0; $i < UserPreferenceRegistry::FAVORITES_MAX; $i++) {
            $slots[] = $selected[$i] ?? '';
        }

        return view('settings.favorites', [
            'availableGroups' => $available,
            'slots' => $slots,
            'maxFavorites' => UserPreferenceRegistry::FAVORITES_MAX,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'favorites' => ['nullable', 'array', 'max:'.UserPreferenceRegistry::FAVORITES_MAX],
            'favorites.*' => ['nullable', 'string', 'max:100'],
        ]);

        $keys = collect($validated['favorites'] ?? [])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();

        try {
            $this->favorites->updateFavoriteKeys($request->user(), $keys);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('favorites.edit')
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('favorites.edit')
            ->with('success', 'Favorite links saved.');
    }
}
