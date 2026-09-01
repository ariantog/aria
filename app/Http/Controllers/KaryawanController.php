<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Karyawan;
use App\Services\Payroll\CutiSisaService;
use App\Support\KaryawanVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KaryawanController extends Controller
{
    public function __construct(
        protected CutiSisaService $cutiSisa,
    ) {}

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
                    ->orWhere('nama_absensi', 'LIKE', "%{$search}%")
                    ->orWhere('absen_id', 'LIKE', "%{$search}%");
            });
        }

        $karyawans = $query->paginate(50)->withQueryString();
        $sisaByKaryawan = $this->cutiSisa->leftoverMap(
            $karyawans->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $now->year,
        );

        return view('karyawan.index', [
            'karyawans' => $karyawans,
            'sisaByKaryawan' => $sisaByKaryawan,
            'cutiLimits' => $this->cutiSisa->defaultLimits(),
            'cutiYear' => $now->year,
            'filters' => $request->only('name'),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function create()
    {
        Gate::authorize(Karyawan::getPermissions()['create']);

        $banks = Addrbook::where('type', Addrbook::TYPE_BANK)->get(['id', 'name']);

        $limits = $this->cutiSisa->defaultLimits();

        return view('karyawan.form', [
            'banks' => $banks,
            'karyawan' => null,
            'cutiLimits' => $limits,
            'cutiSisa' => [
                'sisa_tahunan' => $limits['tahunan'],
                'sisa_sakit' => $limits['sakit'],
                'exists' => false,
                'tahun' => now()->year,
            ],
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['create']);

        $validated = $this->validatedKaryawan($request);
        $karyawan = Karyawan::create($validated);
        $this->syncCutiSisaFromRequest($request, $karyawan);

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
            'cutiLimits' => $this->cutiSisa->defaultLimits(),
            'cutiSisa' => $this->cutiSisa->leftover($karyawan, now()->year),
        ]);
    }

    public function update(Request $request, Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['edit']);
        $this->authorizeKaryawan($request->user(), $karyawan);

        $karyawan->update($this->validatedKaryawan($request, $karyawan));
        $this->syncCutiSisaFromRequest($request, $karyawan);

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
            'absensiHari' => fn ($q) => $q->orderByDesc('tanggal')->limit(40),
            'cutiSisaLogs' => fn ($q) => $q->with('user')->orderByDesc('id')->limit(40),
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
            'cutiSisa' => $this->cutiSisa->leftover($karyawan, now()->year),
            'cutiLimits' => $this->cutiSisa->defaultLimits(),
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
            'absen_id' => 'nullable|string|max:64',
            'jam_kerja' => 'nullable|integer|min:1|max:16',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:255',
            'bulanan' => 'required|numeric',
            'harian' => 'required|numeric',
            'bank_id' => 'required|exists:customers,id',
            'flag' => 'required|integer|in:1,2',
            'waktu_dibatasi' => 'nullable|boolean',
            'jam_masuk' => 'nullable|date_format:H:i',
            'grace_period_menit' => 'nullable|integer|min:0|max:180',
            'sisa_tahunan' => 'nullable|integer|min:0|max:366',
            'sisa_sakit' => 'nullable|integer|min:0|max:366',
            'sisa_catatan' => 'nullable|string|max:255',
        ], [], [
            'nama' => 'nama',
            'nama_absensi' => 'nama absensi',
            'absen_id' => 'ID absensi',
            'jam_kerja' => 'jam kerja',
            'alamat' => 'alamat',
            'no_telp' => 'telepon',
            'bulanan' => 'gaji bulanan',
            'harian' => 'tarif harian',
            'bank_id' => 'rekening bank',
            'flag' => 'privasi',
            'jam_masuk' => 'jam masuk',
            'grace_period_menit' => 'grace period',
            'sisa_tahunan' => 'sisa cuti tahunan',
            'sisa_sakit' => 'sisa cuti sakit',
        ]);

        $validated['nama_absensi'] = filled($validated['nama_absensi'] ?? null)
            ? trim((string) $validated['nama_absensi'])
            : null;
        $validated['absen_id'] = filled($validated['absen_id'] ?? null)
            ? trim((string) $validated['absen_id'])
            : null;
        $this->assertUniqueAbsenId($validated['absen_id'], $karyawan);
        $validated['jam_kerja'] = (int) ($validated['jam_kerja'] ?? 8);
        if ($validated['jam_kerja'] < 1) {
            $validated['jam_kerja'] = 8;
        }
        $validated['waktu_dibatasi'] = $request->boolean('waktu_dibatasi');
        $validated['jam_masuk'] = $validated['jam_masuk'] ?? '08:00';
        $validated['grace_period_menit'] = $validated['grace_period_menit'] ?? null;
        $validated['premi'] = 0;
        unset($validated['sisa_tahunan'], $validated['sisa_sakit'], $validated['sisa_catatan']);

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

    protected function assertUniqueAbsenId(?string $absenId, ?Karyawan $karyawan = null): void
    {
        if (! filled($absenId)) {
            return;
        }

        $exists = Karyawan::query()
            ->whereRaw('LOWER(absen_id) = ?', [mb_strtolower($absenId)])
            ->when($karyawan, fn ($query) => $query->where('id', '!=', $karyawan->id))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'absen_id' => 'ID absensi sudah dipakai karyawan lain (pencarian tidak membedakan huruf besar/kecil).',
            ]);
        }
    }

    protected function syncCutiSisaFromRequest(Request $request, Karyawan $karyawan): void
    {
        if (! $request->exists('sisa_tahunan') && ! $request->exists('sisa_sakit')) {
            return;
        }

        $this->cutiSisa->setManual(
            $karyawan,
            now()->year,
            (int) $request->input('sisa_tahunan', $this->cutiSisa->defaultLimits()['tahunan']),
            (int) $request->input('sisa_sakit', $this->cutiSisa->defaultLimits()['sakit']),
            $request->user(),
            filled($request->input('sisa_catatan')) ? trim((string) $request->input('sisa_catatan')) : 'Dari formulir karyawan',
        );
    }
}
