<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\Karyawan;
use App\Services\Payroll\CutiSisaService;
use App\Support\KaryawanVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CutiController extends Controller
{
    public function __construct(
        protected CutiSisaService $cutiSisa,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-list']);

        $query = Cuti::query()
            ->with('karyawan')
            ->orderByDesc('tgl_mulai')
            ->orderByDesc('id');

        $query->whereHas('karyawan', function ($karyawanQuery) use ($request) {
            KaryawanVisibility::scopeVisibleKaryawan($karyawanQuery, $request->user());
        });

        if ($request->query('karyawan')) {
            $search = (string) $request->query('karyawan');
            $query->whereHas('karyawan', function ($karyawanQuery) use ($search) {
                $karyawanQuery->where('nama', 'like', "%{$search}%")
                    ->orWhere('nama_absensi', 'like', "%{$search}%")
                    ->orWhere('absen_id', 'like', "%{$search}%");
            });
        }

        if ($request->query('tipe')) {
            $query->where('tipe', (int) $request->query('tipe'));
        }

        if ($request->query('tahun')) {
            $year = (int) $request->query('tahun');
            $query->where(function ($inner) use ($year) {
                $inner->whereYear('tgl_mulai', $year)
                    ->orWhereYear('tgl_akhir', $year);
            });
        }

        return view('cuti.index', [
            'cutis' => $query->paginate(30)->withQueryString(),
            'filters' => [
                'karyawan' => $request->query('karyawan'),
                'tipe' => $request->query('tipe'),
                'tahun' => $request->query('tahun'),
            ],
            'types' => Cuti::$types,
            'can' => [
                'create' => $request->user()?->can(Karyawan::getPermissions()['cuti-create']) ?? false,
                'edit' => $request->user()?->can(Karyawan::getPermissions()['cuti-edit']) ?? false,
                'delete' => $request->user()?->can(Karyawan::getPermissions()['cuti-delete']) ?? false,
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function create(Request $request, ?Karyawan $karyawan = null)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-create']);

        if (! $karyawan && $request->query('karyawan_id')) {
            $karyawan = Karyawan::query()->findOrFail($request->query('karyawan_id'));
        }

        if ($karyawan) {
            $this->authorizeKaryawan($request->user(), $karyawan);
        }

        return view('cuti.form', [
            'cuti' => null,
            'karyawan' => $karyawan,
            'karyawans' => $this->visibleKaryawans($request->user()),
        ]);
    }

    public function store(Request $request, ?Karyawan $karyawan = null)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-create']);

        $validated = $this->validatedCuti($request, $karyawan === null);
        $karyawan = $karyawan ?? Karyawan::query()->findOrFail($validated['karyawan_id']);
        $this->authorizeKaryawan($request->user(), $karyawan);

        $cuti = new Cuti;
        $this->fillCuti($cuti, $karyawan, $validated);
        $cuti->save();
        $this->cutiSisa->applyCutiChange($karyawan, null, $cuti, $request->user());

        return redirect()
            ->route('karyawan.show', $karyawan)
            ->with('success', 'Cuti berhasil dicatat.');
    }

    public function edit(Cuti $cuti)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-edit']);
        $cuti->load('karyawan');
        $this->authorizeKaryawan(request()->user(), $cuti->karyawan);

        return view('cuti.form', [
            'cuti' => $cuti,
            'karyawan' => $cuti->karyawan,
            'karyawans' => $this->visibleKaryawans(request()->user()),
        ]);
    }

    public function update(Request $request, Cuti $cuti)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-edit']);
        $cuti->load('karyawan');
        $this->authorizeKaryawan($request->user(), $cuti->karyawan);

        $validated = $this->validatedCuti($request, false);
        $before = $cuti->replicate();
        $this->fillCuti($cuti, $cuti->karyawan, $validated);
        $cuti->save();
        $this->cutiSisa->applyCutiChange($cuti->karyawan, $before, $cuti, $request->user());

        return redirect()
            ->route('karyawan.show', $cuti->karyawan)
            ->with('success', 'Cuti berhasil diperbarui.');
    }

    public function destroy(Request $request, Cuti $cuti)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-delete']);
        $cuti->load('karyawan');
        $this->authorizeKaryawan($request->user(), $cuti->karyawan);

        $karyawan = $cuti->karyawan;
        $snapshot = $cuti->replicate();
        $cuti->delete();
        if ($karyawan) {
            $this->cutiSisa->applyCutiChange($karyawan, $snapshot, null, $request->user());
        }

        return redirect()
            ->route('cuti.index')
            ->with('success', 'Cuti '.$karyawan?->nama.' berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCuti(Request $request, bool $requireKaryawanId): array
    {
        $rules = [
            'tgl_mulai' => 'required|date',
            'tgl_akhir' => 'required|date|after_or_equal:tgl_mulai',
            'tipe' => ['required', 'integer', Rule::in(array_keys(Cuti::$types))],
        ];

        if ($requireKaryawanId) {
            $rules['karyawan_id'] = 'required|integer|exists:karyawans,id';
        }

        return $request->validate($rules, [], [
            'karyawan_id' => 'Karyawan',
            'tgl_mulai' => 'Tanggal Mulai',
            'tgl_akhir' => 'Tanggal Akhir',
            'tipe' => 'Tipe Cuti',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillCuti(Cuti $cuti, Karyawan $karyawan, array $validated): void
    {
        $startDate = Carbon::parse($validated['tgl_mulai']);
        $endDate = Carbon::parse($validated['tgl_akhir']);
        $days = $startDate->diffInDays($endDate) + 1;

        $cuti->karyawan_id = $karyawan->id;
        $cuti->tipe = (int) $validated['tipe'];
        $cuti->tgl_mulai = $validated['tgl_mulai'];
        $cuti->tgl_akhir = $validated['tgl_akhir'];
        $cuti->applyTypeDays($days);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Karyawan>
     */
    private function visibleKaryawans($user)
    {
        $query = Karyawan::query()->orderBy('nama');
        KaryawanVisibility::scopeVisibleKaryawan($query, $user);

        return $query->get(['id', 'nama', 'nama_absensi']);
    }

    protected function authorizeKaryawan($user, ?Karyawan $karyawan): void
    {
        if (! $karyawan || ! KaryawanVisibility::canViewKaryawanRecord($user, $karyawan)) {
            abort(404);
        }
    }
}
