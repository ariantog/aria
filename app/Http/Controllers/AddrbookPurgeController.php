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

    public function destroy(Request $request, DataRetentionService $retention): RedirectResponse
    {
        DataRetentionRun::authorizeManage();

        $validated = $request->validate([
            'addrbook_id' => ['required', 'integer', 'exists:customers,id'],
            'confirm' => ['required', 'string', 'in:DELETE-ADDRBOOK'],
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

        return redirect()
            ->route('data-retention.addrbook-purge.index')
            ->with('success', sprintf(
                'Deleted %s (%s #%d).',
                $preview['name'],
                $preview['type_label'],
                $addrbookId,
            ));
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
