<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Services\Items\ItemLegacyDisplayNameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItemLegacyDisplayNameController extends Controller
{
    public function __construct(
        protected ItemLegacyDisplayNameService $displayNameService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeTool();

        $needle = trim((string) $request->query('needle', ''));
        $parentKey = trim((string) $request->query('parent_key', ''));
        $itemType = ItemType::tryFrom((int) $request->query('type', ItemType::ITEM->value))
            ?? ItemType::ITEM;

        $parentKeys = [];
        $preview = null;

        if ($needle !== '') {
            $parentKeys = $this->displayNameService->parentKeysForNeedle($needle, $itemType);
            if ($parentKey === '' && count($parentKeys) === 1) {
                $parentKey = $parentKeys[0];
            }
        }

        if ($parentKey !== '') {
            $allowed = $parentKeys === [] || in_array($parentKey, $parentKeys, true);
            if ($allowed) {
                try {
                    $preview = $this->displayNameService->previewForParentKey($parentKey);
                } catch (\Throwable) {
                    $preview = null;
                }
            }
        }

        return view('items.display-name-sync', [
            'needle' => $needle,
            'parentKey' => $parentKey,
            'itemType' => $itemType,
            'parentKeys' => $parentKeys,
            'preview' => $preview,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $this->authorizeTool();

        $validated = $request->validate([
            'parent_key' => 'required|string|max:255',
            'product_title' => 'nullable|string|max:255',
            'needle' => 'nullable|string|max:100',
            'type' => 'nullable|integer',
        ]);

        $parentKey = trim($validated['parent_key']);
        $productTitle = isset($validated['product_title']) ? trim($validated['product_title']) : null;
        if ($productTitle === '') {
            $productTitle = null;
        }

        try {
            $result = $this->displayNameService->applyForParentKey($parentKey, $productTitle);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('items.display-name-sync', array_filter([
                    'needle' => $validated['needle'] ?? null,
                    'parent_key' => $parentKey,
                    'type' => $validated['type'] ?? ItemType::ITEM->value,
                ]))
                ->with('error', $e->getMessage());
        }

        $needle = trim((string) ($validated['needle'] ?? ''));

        return redirect()
            ->route('items.display-name-sync', array_filter([
                'needle' => $needle !== '' ? $needle : null,
                'parent_key' => $parentKey,
                'type' => $validated['type'] ?? ItemType::ITEM->value,
            ]))
            ->with(
                'success',
                sprintf(
                    'Updated display names for %d SKU(s). Product title: %s.',
                    $result['updated'],
                    $result['product_title'],
                ),
            );
    }

    protected function authorizeTool(): void
    {
        $user = auth()->user();

        if ($user?->is_superadmin) {
            return;
        }

        Gate::authorize(Item::getPermissions()['convert-legacy']);
    }
}
