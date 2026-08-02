<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class KaryawanController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['list']);

        $now = now();
        $query = Karyawan::with(['gajiSingle', 'bank'])
            ->withSum(['gaji as total_cuti_sakit' => fn ($q) => $q->where('tahun', $now->year)], 'cuti_sakit')
            ->withSum(['gaji as total_cuti_tahunan' => fn ($q) => $q->where('tahun', $now->year)], 'cuti_tahunan')
            ->withSum(['gaji as total_cuti_mendadak' => fn ($q) => $q->where('tahun', $now->year)], 'cuti_mendadak')
            ->orderBy('nama', 'asc');

        if ($request->name) {
            $query->where('nama', 'LIKE', "%{$request->name}%");
        }

        if (auth()->user() && ! auth()->user()->is_superadmin) {
            $query->where('flag', 1);
        }

        $karyawans = $query->paginate(50)->withQueryString();

        return Inertia::render('Karyawan/Index', [
            'karyawans' => $karyawans,
            'filters' => $request->only('name'),
        ]);
    }

    public function create()
    {
        Gate::authorize(Karyawan::getPermissions()['create']);

        $banks = Addrbook::where('type', Addrbook::TYPE_BANK)->get(['id', 'name']);

        return Inertia::render('Karyawan/Form', [
            'banks' => $banks,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['create']);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:255',
            'bulanan' => 'required|numeric',
            'harian' => 'required|numeric',
            'premi' => 'required|numeric',
            'bank_id' => 'required|exists:addrbooks,id',
            'flag' => 'required|integer',
        ], [], [
            'nama' => 'Name',
            'alamat' => 'Address',
            'no_telp' => 'Phone',
            'bulanan' => 'Bulanan',
            'harian' => 'Harian',
            'premi' => 'Premi',
            'bank_id' => 'Account bank',
            'flag' => 'Privasi',
        ]);

        Karyawan::create($validated);

        return redirect()->route('karyawan.index')->with('success', 'Karyawan '.$request->nama.' created.');
    }

    public function edit(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['edit']);

        if (auth()->user() && ! auth()->user()->is_superadmin) {
            if ($karyawan->flag == 2) {
                abort(404);
            }
        }

        $banks = Addrbook::where('type', Addrbook::TYPE_BANK)->get(['id', 'name']);

        return Inertia::render('Karyawan/Form', [
            'karyawan' => $karyawan,
            'banks' => $banks,
        ]);
    }

    public function update(Request $request, Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['edit']);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:255',
            'bulanan' => 'required|numeric',
            'harian' => 'required|numeric',
            'premi' => 'required|numeric',
            'bank_id' => 'required|exists:addrbooks,id',
            'flag' => 'required|integer',
        ]);

        $karyawan->update($validated);

        return redirect()->route('karyawan.index')->with('success', 'Karyawan '.$karyawan->nama.' updated.');
    }

    public function show(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['list']);

        if (auth()->user() && ! auth()->user()->is_superadmin && $karyawan->flag == 2) {
            abort(404);
        }

        $karyawan->load([
            'bank',
            'gaji' => fn ($q) => $q->orderBy('tahun', 'desc')->orderBy('bulan', 'desc'),
            'cuti' => fn ($q) => $q->orderBy('tgl_mulai', 'desc'),
        ]);

        return Inertia::render('Karyawan/Show', [
            'karyawan' => $karyawan,
        ]);
    }

    public function destroy(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['delete']);

        $karyawan->delete();

        return redirect()->route('karyawan.index')->with('success', 'Karyawan '.$karyawan->nama.' deleted.');
    }
}
