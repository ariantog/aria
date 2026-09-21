<?php

namespace App\Http\Controllers;

use App\Models\AbsensiHari;
use App\Models\AbsensiImport;
use App\Models\Karyawan;
use App\Services\Payroll\AbsensiImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class AbsensiController extends Controller
{
    public function __construct(
        protected AbsensiImportService $importer,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['absensi-list']);

        $imports = AbsensiImport::query()
            ->with('user')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate(20);

        return view('absensi.index', [
            'imports' => $imports,
            'canImport' => $request->user()?->can(Karyawan::getPermissions()['absensi-import']) ?? false,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function create()
    {
        Gate::authorize(Karyawan::getPermissions()['absensi-import']);

        return view('absensi.create', [
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['absensi-import']);

        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx|max:10240',
        ], [], [
            'file' => 'file absensi',
        ]);

        try {
            $result = $this->importer->import($request->file('file'), $request->user());
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['file' => $exception->getMessage()]);
        }

        $import = $result['import'];

        return redirect()
            ->route('absensi.show', $import)
            ->with('success', 'Absensi diimpor. '.$import->matched_count.' ID cocok, '.$import->unmatched_count.' belum terhubung.');
    }

    public function show(AbsensiImport $absensi)
    {
        Gate::authorize(Karyawan::getPermissions()['absensi-list']);

        $days = AbsensiHari::query()
            ->where('import_id', $absensi->id)
            ->with('karyawan')
            ->orderBy('absen_id')
            ->orderBy('tanggal')
            ->get();

        $grouped = $days->groupBy(fn (AbsensiHari $day) => mb_strtolower((string) $day->absen_id));

        $employees = $grouped->map(function ($rows) {
            /** @var \Illuminate\Support\Collection<int, AbsensiHari> $rows */
            $first = $rows->first();

            return [
                'absen_id' => $first->absen_id,
                'nama_mesin' => $first->nama_mesin,
                'karyawan' => $first->karyawan,
                'jam_total' => round((float) $rows->sum('jam'), 2),
                'incomplete' => $rows->where('incomplete', true)->count(),
                'days' => $rows->values(),
            ];
        })->values();

        $unmatched = $employees->filter(fn (array $row) => $row['karyawan'] === null)->values();

        return view('absensi.show', [
            'import' => $absensi,
            'employees' => $employees,
            'unmatched' => $unmatched,
            'dates' => $days->pluck('tanggal')->map(fn ($d) => $d->toDateString())->unique()->sort()->values(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
