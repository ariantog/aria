<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\DataRetentionRun;
use App\Services\DataRetentionService;
use App\Support\LikeSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AddrbookPurgeController extends Controller
{
    public function index(DataRetentionService $retention): View
    {
        DataRetentionRun::authorizeManage();

        $addrbookTypes = $this->addrbookTypeOptions();
        $selectedType = $this->selectedType(request()->query('type'), $addrbookTypes);
        $list = null;
        $totalCandidates = 0;

        if ($selectedType !== null) {
            $totalCandidates = $retention->countDeletableAddrbooks($selectedType);
            $list = $retention->paginateDeletableAddrbooks($selectedType);
        }

        $addrbookId = request()->query('addrbook_id');
        $preview = null;

        if ($addrbookId !== null && $addrbookId !== '' && ctype_digit((string) $addrbookId)) {
            $preview = $retention->previewAddrbookPurge((int) $addrbookId);
        }

        $lookupId = old('addrbook_id', $addrbookId);
        $addrbookInitial = null;

        if ($lookupId !== null && $lookupId !== '' && ctype_digit((string) $lookupId)) {
            $addrbook = Addrbook::withTrashed()->find((int) $lookupId);

            if ($addrbook) {
                $addrbookInitial = $this->lookupItem($addrbook);
            }
        }

        return view('system-settings.addrbook-purge', [
            'lookupUrl' => route('data-retention.addrbook-purge.lookup'),
            'addrbookTypes' => $addrbookTypes,
            'selectedType' => $selectedType,
            'selectedTypeLabel' => $selectedType !== null
                ? Addrbook::typeLabel($selectedType)
                : null,
            'totalCandidates' => $totalCandidates,
            'list' => $list,
            'addrbookInitial' => $addrbookInitial,
            'preview' => $preview,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        DataRetentionRun::authorizeManage();

        $query = Addrbook::withTrashed();

        if ($search = $request->query('search')) {
            $pattern = LikeSearch::contains((string) $search);
            $query->where(function ($builder) use ($pattern, $search) {
                $builder->where('name', 'like', $pattern)
                    ->orWhere('memberId', 'like', $pattern);

                if (ctype_digit((string) $search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $items = $query->orderBy('name')->orderBy('id')->limit(20)->get()
            ->map(fn (Addrbook $row) => $this->lookupItem($row))
            ->values();

        return response()->json($items);
    }

    public function purge(Request $request, DataRetentionService $retention): RedirectResponse
    {
        DataRetentionRun::authorizeManage();

        $addrbookTypes = $this->addrbookTypeOptions();

        $validated = $request->validate([
            'type' => ['required', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_addrbook_ids' => ['required', 'array', 'min:1'],
            'page_addrbook_ids.*' => ['integer', 'min:1'],
            'keep_ids' => ['nullable', 'array'],
            'keep_ids.*' => ['integer', 'min:1'],
            'confirm' => ['required', 'string', 'in:DELETE-ADDRBOOK'],
        ]);

        $type = (int) $validated['type'];

        if (! array_key_exists($type, $addrbookTypes)) {
            return back()->with('error', 'Invalid addrbook type.');
        }

        $page = isset($validated['page']) ? (int) $validated['page'] : 1;
        $pageAddrbookIds = $this->normalizeIds($validated['page_addrbook_ids']);
        $keepIds = $this->normalizeIds($validated['keep_ids'] ?? []);
        $purgeIds = array_values(array_diff($pageAddrbookIds, $keepIds));

        if ($purgeIds === []) {
            return back()->with('error', 'No addrbooks selected for deletion on this page.');
        }

        try {
            $purged = $retention->purgeDeletableAddrbooksByIds($type, $purgeIds);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('data-retention.addrbook-purge.index', array_filter([
                'type' => $type,
                'page' => $page > 1 ? $page : null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', sprintf(
                'Deleted %d addrbook(s) on page %d. %d row(s) on this page were kept.',
                $purged,
                $page,
                count($keepIds),
            ));
    }

    public function destroy(Request $request, DataRetentionService $retention): RedirectResponse
    {
        DataRetentionRun::authorizeManage();

        $validated = $request->validate([
            'addrbook_id' => ['required', 'integer', 'exists:customers,id'],
            'confirm' => ['required', 'string', 'in:DELETE-ADDRBOOK'],
            'type' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $addrbookId = (int) $validated['addrbook_id'];

        try {
            $preview = $retention->previewAddrbookPurge($addrbookId);

            if ($preview === null) {
                return back()->withInput()->with('error', 'Addrbook not found.');
            }

            if (! $preview['deletable']) {
                return back()->withInput()->with('error', 'This addrbook appears in the transactions table and cannot be deleted.');
            }

            $retention->deleteAddrbookFromLive($addrbookId);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $redirectParams = array_filter([
            'type' => isset($validated['type']) ? (int) $validated['type'] : null,
            'page' => isset($validated['page']) ? (int) $validated['page'] : null,
        ], fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('data-retention.addrbook-purge.index', $redirectParams)
            ->with('success', sprintf(
                'Deleted %s (%s #%d).',
                $preview['name'],
                $preview['type_label'],
                $addrbookId,
            ));
    }

    /**
     * @return array<int, string>
     */
    private function addrbookTypeOptions(): array
    {
        $types = array_merge(Addrbook::navigableTypeIds(), [Addrbook::TYPE_OTHER]);

        $options = [];

        foreach ($types as $type) {
            $options[$type] = Addrbook::typeLabel($type);
        }

        return $options;
    }

    /**
     * @param  array<int, string>  $addrbookTypes
     */
    private function selectedType(mixed $type, array $addrbookTypes): ?int
    {
        if ($type === null || $type === '' || ! ctype_digit((string) $type)) {
            return null;
        }

        $type = (int) $type;

        return array_key_exists($type, $addrbookTypes) ? $type : null;
    }

    /**
     * @param  array<int|string>|int|string|null  $ids
     * @return list<int>
     */
    private function normalizeIds(array|int|string|null $ids): array
    {
        if ($ids === null || $ids === '') {
            return [];
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @return array{id: int, name: string}
     */
    private function lookupItem(Addrbook $addrbook): array
    {
        return [
            'id' => (int) $addrbook->id,
            'name' => $addrbook->name.' ('.Addrbook::typeLabel((int) $addrbook->type).' #'.$addrbook->id.')',
        ];
    }
}
