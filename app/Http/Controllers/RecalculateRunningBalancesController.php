<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Support\LikeSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class RecalculateRunningBalancesController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Setting::getPermissions()['view']);

        $oldAddrbookId = old('addrbook_id');
        $addrbook = $oldAddrbookId ? Addrbook::withTrashed()->find($oldAddrbookId) : null;

        return view('system-settings.recalculate-running-balances', [
            'canRun' => request()->user()?->can(Setting::getPermissions()['edit']) ?? false,
            'lookupUrl' => route('recalculate-running-balances.lookup'),
            'addrbookInitial' => $addrbook ? $this->lookupItem($addrbook) : null,
            'coverage' => $this->coverage(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function run(Request $request, TransactionService $service): RedirectResponse
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        $validated = $request->validate([
            'addrbook_id' => ['nullable', 'integer', 'exists:customers,id'],
            'from' => ['nullable', 'date'],
            'confirm' => ['accepted'],
        ]);

        $addrbookId = isset($validated['addrbook_id']) ? (int) $validated['addrbook_id'] : null;
        $from = $validated['from'] ?? null;

        set_time_limit(0);

        try {
            $updated = $service->rebuildRunningBalances($addrbookId, $from);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->errorMessage($e));
        }

        $scope = $addrbookId
            ? 'addrbook #'.$addrbookId
            : 'all contacts';
        $fromLabel = $from ? ' from '.$from : '';

        return back()->with('success', sprintf(
            'Recalculated running balances on %d transaction(s) (%s%s).',
            $updated,
            $scope,
            $fromLabel,
        ));
    }

    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize(Setting::getPermissions()['view']);

        $query = Addrbook::query();

        if ($search = $request->query('search')) {
            $pattern = LikeSearch::contains((string) $search);
            $query->where(function ($q) use ($pattern, $search) {
                $q->where('name', 'like', $pattern)
                    ->orWhere('memberId', 'like', $pattern);

                if (ctype_digit((string) $search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $items = $query->orderBy('name')->orderBy('id')->limit(20)->get()
            ->map(fn (Addrbook $row) => $this->lookupItem($row))
            ->values();

        return response()->json($items);
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

    /**
     * @return array{transactions: int, earliest: ?string, latest: ?string}
     */
    private function coverage(): array
    {
        $bounds = Transaction::query()
            ->selectRaw('COUNT(*) as rows, MIN(date) as earliest, MAX(date) as latest')
            ->first();

        $earliest = $bounds?->earliest ? substr((string) $bounds->earliest, 0, 10) : null;
        $latest = $bounds?->latest ? substr((string) $bounds->latest, 0, 10) : null;

        return [
            'transactions' => (int) ($bounds->rows ?? 0),
            'earliest' => $earliest === '0000-00-00' ? null : $earliest,
            'latest' => $latest === '0000-00-00' ? null : $latest,
        ];
    }

    private function errorMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        $maxLength = 500;

        if (strlen($message) > $maxLength) {
            $message = substr($message, 0, $maxLength - 1).'…';
        }

        return 'Recalculate failed: '.$message;
    }
}
