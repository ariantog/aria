<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Karyawan;
use App\Support\KaryawanVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KaryawanController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['list']);

        $now = now();
        $query = Karyawan::query()
            ->with(['gajiSingle', 'bank'])
            ->withSum(['gaji as total_cuti_sakit' => fn ($q) => $q->where('tahun', $now->year)], 'cuti_sakit')
            ->withSum(['gaji as total_cuti_tahunan' => fn ($q) => $q->where('tahun', $now->year)], 'cuti_tahunan')
            ->withSum(['gaji as total_cuti_mendadak' => fn ($q) => $q->where('tahun', $now->year)], 'cuti_mendadak')
            ->orderBy('nama', 'asc');

        KaryawanVisibility::scopeVisibleKaryawan($query, $request->user());

        if ($request->name) {
            $search = (string) $request->name;
            $query->where(function ($inner) use ($search) {
                $inner->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('nama_absensi', 'LIKE', "%{$search}%");
            });
        }

        $karyawans = $query->paginate(50)->withQueryString();

        return view('karyawan.index', [
            'karyawans' => $karyawans,
            'filters' => $request->only('name'),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function create()
    {
        Gate::authorize(Karyawan::getPermissions()['create']);

        $banks = Addrbook::where('type', Addrbook::TYPE_BANK)->get(['id', 'name']);

        return view('karyawan.form', [
            'banks' => $banks,
            'karyawan' => null,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['create']);

        $validated = $this->validatedKaryawan($request);
        Karyawan::create($validated);

        return redirect()->route('karyawan.index')->with('success', 'Karyawan '.$request->nama.' berhasil ditambahkan.');
    }

    public function edit(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['edit']);
        $this->authorizeKaryawan(request()->user(), $karyawan);

        $banks = Addrbook::where('type', Addrbook::TYPE_BANK)->get(['id', 'name']);

        return view('karyawan.form', [
            'karyawan' => $karyawan,
            'banks' => $banks,
        ]);
    }

    public function update(Request $request, Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['edit']);
        $this->authorizeKaryawan($request->user(), $karyawan);

        $karyawan->update($this->validatedKaryawan($request, $karyawan));

        return redirect()->route('karyawan.index')->with('success', 'Karyawan '.$karyawan->nama.' berhasil diperbarui.');
    }

    public function show(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['list']);
        $this->authorizeKaryawan(request()->user(), $karyawan);

        $karyawan->load([
            'bank',
            'gaji' => fn ($q) => $q->orderBy('tahun', 'desc')->orderBy('bulan', 'desc'),
            'cuti' => fn ($q) => $q->orderBy('tgl_mulai', 'desc'),
        ]);

        $user = request()->user();
        if (! KaryawanVisibility::isSuperadmin($user)) {
            $karyawan->setRelation(
                'gaji',
                $karyawan->gaji->filter(
                    fn ($gaji) => KaryawanVisibility::canViewGajiRecord($user, $gaji)
                )->values()
            );
        }

        return view('karyawan.show', [
            'karyawan' => $karyawan,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function destroy(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['delete']);
        $this->authorizeKaryawan(request()->user(), $karyawan);

        $karyawan->delete();

        return redirect()->route('karyawan.index')->with('success', 'Karyawan '.$karyawan->nama.' berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedKaryawan(Request $request, ?Karyawan $karyawan = null): array
    {
        $user = $request->user();
        $canSetPrivate = KaryawanVisibility::isSuperadmin($user);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nama_absensi' => 'nullable|string|max:255',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:255',
            'bulanan' => 'required|numeric',
            'harian' => 'required|numeric',
            'bank_id' => 'required|exists:customers,id',
            'flag' => 'required|integer|in:1,2',
            'waktu_dibatasi' => 'nullable|boolean',
            'jam_masuk' => 'nullable|date_format:H:i',
            'grace_period_menit' => 'nullable|integer|min:0|max:180',
        ], [], [
            'nama' => 'nama',
            'nama_absensi' => 'nama absensi',
            'alamat' => 'alamat',
            'no_telp' => 'telepon',
            'bulanan' => 'gaji bulanan',
            'harian' => 'tarif harian',
            'bank_id' => 'rekening bank',
            'flag' => 'privasi',
            'jam_masuk' => 'jam masuk',
            'grace_period_menit' => 'grace period',
        ]);

        $validated['nama_absensi'] = filled($validated['nama_absensi'] ?? null)
            ? trim((string) $validated['nama_absensi'])
            : null;
        $validated['waktu_dibatasi'] = $request->boolean('waktu_dibatasi');
        $validated['jam_masuk'] = $validated['jam_masuk'] ?? '08:00';
        $validated['grace_period_menit'] = $validated['grace_period_menit'] ?? null;
        $validated['premi'] = 0;

        if (! $canSetPrivate && (int) $validated['flag'] === KaryawanVisibility::FLAG_PRIVATE) {
            $validated['flag'] = KaryawanVisibility::FLAG_PUBLIC;
        }

        if ($karyawan && (int) $karyawan->flag === KaryawanVisibility::FLAG_PRIVATE && ! $canSetPrivate) {
            $validated['flag'] = KaryawanVisibility::FLAG_PRIVATE;
        }

        return $validated;
    }

    protected function authorizeKaryawan($user, Karyawan $karyawan): void
    {
        if (! KaryawanVisibility::canViewKaryawanRecord($user, $karyawan)) {
            abort(404);
        }
    }
}
