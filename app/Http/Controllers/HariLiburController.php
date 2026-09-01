<?php

namespace App\Http\Controllers;

use App\Models\HariLibur;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HariLiburController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['hari-libur-list']);

        $year = (int) ($request->query('tahun') ?: now()->year);

        $libur = HariLibur::query()
            ->whereYear('tanggal', $year)
            ->orderBy('tanggal')
            ->get();

        return view('hari-libur.index', [
            'libur' => $libur,
            'year' => $year,
            'can' => [
                'create' => $request->user()?->can(Karyawan::getPermissions()['hari-libur-create']) ?? false,
                'delete' => $request->user()?->can(Karyawan::getPermissions()['hari-libur-delete']) ?? false,
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['hari-libur-create']);

        $validated = $request->validate([
            'tanggal' => 'required|date|unique:hari_libur,tanggal',
            'nama' => 'required|string|max:255',
            'catatan' => 'nullable|string|max:255',
        ], [], [
            'tanggal' => 'tanggal',
            'nama' => 'nama libur',
            'catatan' => 'catatan',
        ]);

        HariLibur::query()->create($validated);

        return redirect()
            ->route('hari-libur.index', ['tahun' => date('Y', strtotime($validated['tanggal']))])
            ->with('success', 'Hari libur ditambahkan.');
    }

    public function destroy(HariLibur $hari_libur)
    {
        Gate::authorize(Karyawan::getPermissions()['hari-libur-delete']);

        $year = $hari_libur->tanggal?->year ?: now()->year;
        $hari_libur->delete();

        return redirect()
            ->route('hari-libur.index', ['tahun' => $year])
            ->with('success', 'Hari libur dihapus.');
    }
}
