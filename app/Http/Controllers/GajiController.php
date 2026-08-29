<?php

namespace App\Http\Controllers;

use App\Models\Gaji;
use App\Models\Karyawan;
use App\Services\Payroll\GajiSalaryCalculator;
use App\Support\KaryawanVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GajiController extends Controller
{
    public function __construct(
        protected GajiSalaryCalculator $calculator,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-list']);

        $user = $request->user();
        $bulanSelect = (int) ($request->bulan ?: now()->month);
        $yearSelect = (int) ($request->tahun ?: now()->year);

        $query = Gaji::query()
            ->with(['karyawan', 'bankSingle'])
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc');

        KaryawanVisibility::scopeVisibleGaji($query, $user);

        $query->where('bulan', $bulanSelect)->where('tahun', $yearSelect);

        if ($request->karyawan) {
            $query->whereHas('karyawan', function ($q) use ($request) {
                $q->where('nama', 'like', "%{$request->karyawan}%");
            });
        }

        $gajiList = $query->paginate(20)->withQueryString();

        $bankQuery = Gaji::query()
            ->where('bulan', $bulanSelect)
            ->where('tahun', $yearSelect);
        KaryawanVisibility::scopeVisibleGaji($bankQuery, $user);

        $gajiPerBank = (clone $bankQuery)
            ->selectRaw('bank_id, SUM(total_gaji) as total_gaji')
            ->groupBy('bank_id')
            ->with('bank')
            ->get();

        return view('gaji.index', [
            'gajiList' => $gajiList,
            'bulanSelect' => $bulanSelect,
            'yearSelect' => $yearSelect,
            'gajiPerBank' => $gajiPerBank,
            'filters' => $request->only(['bulan', 'tahun', 'karyawan']),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function create(Request $request, Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-create']);
        $this->authorizeKaryawanAccess($request->user(), $karyawan);

        $bulan = (int) ($request->query('bulan') ?: now()->month);
        $tahun = (int) ($request->query('tahun') ?: now()->year);

        $existing = Gaji::query()
            ->where('karyawan_id', $karyawan->id)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if ($existing) {
            return redirect()->route('gaji.edit', $existing);
        }

        $calculation = $this->calculator->calculate($karyawan, $bulan, $tahun);

        return view('gaji.form', [
            'karyawan' => $karyawan,
            'gaji' => null,
            'calculation' => $calculation,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request, Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-create']);
        $this->authorizeKaryawanAccess($request->user(), $karyawan);

        $payload = $this->validatedPayload($request, $karyawan);
        $this->assertUniquePeriod($karyawan, $payload['bulan'], $payload['tahun']);

        Gaji::create($this->buildGajiAttributes($karyawan, $payload));

        return redirect()
            ->route('karyawan.show', $karyawan)
            ->with('success', 'Gaji '.$karyawan->nama.' berhasil disimpan.');
    }

    public function edit(Request $request, Gaji $gaji)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-edit']);
        $this->authorizeGajiAccess($request->user(), $gaji);

        $gaji->load('karyawan');
        $karyawan = $gaji->karyawan;

        if (! $karyawan) {
            abort(404);
        }

        $calculation = $this->calculator->calculate(
            $karyawan,
            (int) $gaji->bulan,
            (int) $gaji->tahun,
            (int) $gaji->bonus,
            (int) $gaji->sanksi,
            (int) $gaji->potongan_harian,
            (int) $gaji->potongan_telat,
            (int) $gaji->upah_lembur,
            (int) $gaji->cuti_tahunan,
            (int) $gaji->cuti_sakit,
            (int) $gaji->cuti_mendadak,
            (int) $gaji->hari_izin,
            (int) $gaji->menit_telat,
            (float) $gaji->jam_lembur,
        );

        return view('gaji.form', [
            'karyawan' => $karyawan,
            'gaji' => $gaji,
            'calculation' => $calculation,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Gaji $gaji)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-edit']);
        $this->authorizeGajiAccess($request->user(), $gaji);

        $karyawan = $gaji->karyawan;
        if (! $karyawan) {
            abort(404);
        }

        $payload = $this->validatedPayload($request, $karyawan, $gaji);
        $gaji->update($this->buildGajiAttributes($karyawan, $payload));

        return redirect()
            ->route('karyawan.show', $karyawan)
            ->with('success', 'Gaji '.$karyawan->nama.' berhasil diperbarui.');
    }

    public function destroy(Gaji $gaji)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-delete']);
        $this->authorizeGajiAccess(auth()->user(), $gaji);

        $gaji->delete();

        return redirect()->route('gaji.index')->with('success', 'Gaji berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPayload(Request $request, Karyawan $karyawan, ?Gaji $existing = null): array
    {
        $user = $request->user();
        $canSetPrivate = KaryawanVisibility::isSuperadmin($user);

        $validated = $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
            'bulanan' => 'required|numeric|min:0',
            'harian_rate' => 'required|numeric|min:0',
            'total_cuti_tahunan' => 'required|integer|min:0',
            'total_cuti_sakit' => 'required|integer|min:0',
            'total_cuti_mendadak' => 'required|integer|min:0',
            'hari_izin' => 'required|integer|min:0',
            'potong_harian' => 'required|numeric|min:0',
            'menit_telat' => 'required|integer|min:0',
            'potong_telat' => 'required|numeric|min:0',
            'jam_lembur' => 'required|numeric|min:0',
            'upah_lembur' => 'required|numeric|min:0',
            'bonus' => 'required|numeric|min:0',
            'sanksi' => 'required|numeric|min:0',
            'privasi' => 'required|integer|in:1,2',
        ], [], [
            'bulanan' => 'gaji bulanan',
            'harian_rate' => 'tarif harian',
            'hari_izin' => 'hari izin',
            'potong_harian' => 'potongan harian',
            'menit_telat' => 'menit telat',
            'potong_telat' => 'potongan telat',
            'jam_lembur' => 'jam lembur',
            'upah_lembur' => 'upah lembur',
        ]);

        if (! $canSetPrivate && (int) $validated['privasi'] === KaryawanVisibility::FLAG_PRIVATE) {
            throw ValidationException::withMessages([
                'privasi' => 'Hanya superadmin yang dapat menandai gaji sebagai privasi.',
            ]);
        }

        if ($existing && ((int) $existing->bulan !== (int) $validated['bulan'] || (int) $existing->tahun !== (int) $validated['tahun'])) {
            $this->assertUniquePeriod($karyawan, (int) $validated['bulan'], (int) $validated['tahun'], $existing->id);
        }

        $calculation = $this->calculator->calculate(
            $karyawan,
            (int) $validated['bulan'],
            (int) $validated['tahun'],
            (int) $validated['bonus'],
            (int) $validated['sanksi'],
            (int) $validated['potong_harian'],
            (int) $validated['potong_telat'],
            (int) $validated['upah_lembur'],
            (int) $validated['total_cuti_tahunan'],
            (int) $validated['total_cuti_sakit'],
            (int) $validated['total_cuti_mendadak'],
            (int) $validated['hari_izin'],
            (int) $validated['menit_telat'],
            (float) $validated['jam_lembur'],
        );

        $validated['total_gaji'] = $calculation['total_gaji'];
        $validated['total_cuti'] = $calculation['total_cuti'];
        $validated['total_potongan'] = $calculation['total_potongan'];
        $validated['harian_total'] = (int) $validated['harian_rate'] * GajiSalaryCalculator::WORKING_DAYS_PER_MONTH;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildGajiAttributes(Karyawan $karyawan, array $payload): array
    {
        return [
            'karyawan_id' => $karyawan->id,
            'bulan' => (int) $payload['bulan'],
            'tahun' => (int) $payload['tahun'],
            'bulanan' => (int) $payload['bulanan'],
            'harian' => (int) $payload['harian_total'],
            'cuti_sakit' => (int) $payload['total_cuti_sakit'],
            'cuti_tahunan' => (int) $payload['total_cuti_tahunan'],
            'cuti_mendadak' => (int) $payload['total_cuti_mendadak'],
            'hari_izin' => (int) $payload['hari_izin'],
            'potongan_harian' => (int) $payload['potong_harian'],
            'menit_telat' => (int) $payload['menit_telat'],
            'potongan_telat' => (int) $payload['potong_telat'],
            'jam_lembur' => (float) $payload['jam_lembur'],
            'upah_lembur' => (int) $payload['upah_lembur'],
            'total_potongan' => (int) $payload['total_potongan'],
            'bonus' => (int) $payload['bonus'],
            'sanksi' => (int) $payload['sanksi'],
            'total_gaji' => (int) $payload['total_gaji'],
            'bank_id' => $karyawan->bank_id,
            'flag' => (int) $payload['privasi'],
        ];
    }

    protected function assertUniquePeriod(Karyawan $karyawan, int $bulan, int $tahun, ?int $ignoreId = null): void
    {
        $exists = Gaji::query()
            ->where('karyawan_id', $karyawan->id)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'bulan' => 'Gaji untuk periode ini sudah ada.',
            ]);
        }
    }

    protected function authorizeKaryawanAccess($user, Karyawan $karyawan): void
    {
        if (! KaryawanVisibility::canViewKaryawanRecord($user, $karyawan)) {
            abort(404);
        }
    }

    protected function authorizeGajiAccess($user, Gaji $gaji): void
    {
        if (! KaryawanVisibility::canViewGajiRecord($user, $gaji)) {
            abort(404);
        }
    }
}
