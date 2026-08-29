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
            ->selectRaw('bank_id, SUM('.Gaji::totalColumn().') as total_gaji')
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
            ->with('success', 'Gaji '.$karyawan->nama.' saved.');
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
            (int) $gaji->potongan_cuti_bulanan,
            (int) $gaji->potongan_cuti_premi,
            (int) $gaji->cuti_tahunan,
            (int) $gaji->cuti_sakit,
            (int) $gaji->cuti_mendadak,
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
            ->with('success', 'Gaji '.$karyawan->nama.' updated.');
    }

    public function destroy(Gaji $gaji)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-delete']);
        $this->authorizeGajiAccess(auth()->user(), $gaji);

        $gaji->delete();

        return redirect()->route('gaji.index')->with('success', 'Gaji deleted');
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
            'premi' => 'required|numeric|min:0',
            'total_cuti_tahunan' => 'required|integer|min:0',
            'total_cuti_sakit' => 'required|integer|min:0',
            'total_cuti_mendadak' => 'required|integer|min:0',
            'potong_bulanan' => 'required|numeric|min:0',
            'potong_premi' => 'required|numeric|min:0',
            'bonus' => 'required|numeric|min:0',
            'sanksi' => 'required|numeric|min:0',
            'privasi' => 'required|integer|in:1,2',
        ]);

        if (! $canSetPrivate && (int) $validated['privasi'] === KaryawanVisibility::FLAG_PRIVATE) {
            throw ValidationException::withMessages([
                'privasi' => 'Only the superadmin can mark payroll as private.',
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
            (int) $validated['potong_bulanan'],
            (int) $validated['potong_premi'],
            (int) $validated['total_cuti_tahunan'],
            (int) $validated['total_cuti_sakit'],
            (int) $validated['total_cuti_mendadak'],
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
            'premi' => (int) $payload['premi'],
            'cuti_sakit' => (int) $payload['total_cuti_sakit'],
            'cuti_tahunan' => (int) $payload['total_cuti_tahunan'],
            'cuti_mendadak' => (int) $payload['total_cuti_mendadak'],
            'total_cuti' => (int) $payload['total_cuti'],
            'potongan_cuti_bulanan' => (int) $payload['potong_bulanan'],
            'potongan_cuti_premi' => (int) $payload['potong_premi'],
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
                'bulan' => 'Payroll for this period already exists.',
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
