<?php

namespace App\Http\Controllers;

use App\Jobs\SyncJubelioMissingOrders;
use App\Models\Crongetorder;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\ScheduledTask;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioGetOrderController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $import = Crongetorder::orderByDesc('created_at')->first();
        $recentlyQueued = collect();
        $pollDays = (int) config('services.jubelio.poll_days', 7);

        if ($import) {
            $recentlyQueued = Jubelioorder::query()
                ->where('source', 2)
                ->where('created_at', '>=', $import->created_at)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return view('jubelio.get-orders.index', [
            'import' => $import,
            'recentlyQueued' => $recentlyQueued,
            'pollDays' => $pollDays,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        if (Crongetorder::where('status', 0)->exists()) {
            return back()->with('error', 'Masih ada sinkronisasi yang berjalan. Reset terlebih dahulu.');
        }

        $from = Carbon::parse($validated['date_from']);
        $to = Carbon::parse($validated['date_to']);
        $daySpan = (int) $from->diffInDays($to);

        $import = Crongetorder::create([
            'from' => $from->toDateString(),
            'to' => $daySpan,
        ]);

        SyncJubelioMissingOrders::dispatch($import->id);

        return redirect()->route('jubelio.get-orders.index')
            ->with('success', 'Sinkronisasi dimulai. Order yang belum ada di Aria akan langsung masuk antrian Jubelio Orders.');
    }

    public function reset(): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $import = Crongetorder::orderByDesc('created_at')->first();
        if ($import) {
            $import->delete();
        }

        ScheduledTask::where('command', 'jubelio:get-orders')->update(['is_active' => false]);

        return back()->with('success', 'Sinkronisasi direset.');
    }
}
