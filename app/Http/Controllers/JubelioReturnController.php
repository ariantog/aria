<?php

namespace App\Http\Controllers;

use App\Actions\Jubelio\ProcessJubelioCancellation;
use App\Models\Jubelio;
use App\Models\Jubelioreturn;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioReturnController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $query = Jubelioreturn::with('user')->orderBy('updated_at', 'asc');

        if ($request->status === 'SOLVED') {
            $query->whereIn('status', [1, 2]);
        } else {
            $query->where('status', 0);
        }

        if ($request->from && $request->to) {
            $query->whereDate('updated_at', '>=', $request->from)
                ->whereDate('updated_at', '<=', $request->to);
        }

        if ($request->invoice) {
            $query->where('invoice', 'like', '%'.$request->invoice.'%');
        }

        return view('jubelio.returns.index', [
            'returns' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['status', 'from', 'to', 'invoice']),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function show(Jubelioreturn $jubelioReturn): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $transaction = Transaction::with(['receiver', 'sender', 'user', 'details.item.group'])
            ->findOrFail($jubelioReturn->transaction_id);

        return view('jubelio.returns.show', [
            'return' => $jubelioReturn,
            'transaction' => $transaction,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function process(Request $request, Jubelioreturn $jubelioReturn, ProcessJubelioCancellation $processor): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $validated = $request->validate([
            'return_item' => ['required', 'array', 'min:1'],
            'return_item.*' => ['integer'],
            'adjustment' => ['nullable', 'numeric'],
        ]);

        $result = $processor->execute(
            $jubelioReturn,
            array_map('intval', $validated['return_item']),
            (float) ($validated['adjustment'] ?? 0),
            (int) auth()->id(),
        );

        return $result['success']
            ? redirect()->route('transactions.index')->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    public function markSolved(Jubelioreturn $jubelioReturn): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        if ($jubelioReturn->status !== 0) {
            return back()->with('error', 'Pembatalan ini sudah diproses.');
        }

        $jubelioReturn->update([
            'status' => 1,
            'confirmed_by' => auth()->id(),
        ]);

        return redirect()->route('jubelio.returns.index')->with('success', 'Pembatalan ditandai selesai.');
    }
}
