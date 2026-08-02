<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\Gaji;
use App\Models\Karyawan;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class GajiController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-list']);

        $now = Carbon::now();
        $bulanSelect = $request->bulan ?: $now->month;
        $yearSelect = $request->tahun ?: $now->year;

        $query = Gaji::with(['karyawan', 'bankSingle'])
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc');

        if (auth()->user() && ! auth()->user()->is_superadmin) {
            $query->whereHas('karyawan', function ($q) {
                $q->where('flag', 1);
            });
        }

        $query->where('bulan', $bulanSelect)->where('tahun', $yearSelect);

        if ($request->karyawan) {
            $query->whereHas('karyawan', function ($q) use ($request) {
                $q->where('nama', 'like', "%{$request->karyawan}%");
            });
        }

        $gajiList = $query->paginate(20)->withQueryString();

        $gajiPerBank = Gaji::with('bank')
            ->where('bulan', $bulanSelect)
            ->where('tahun', $yearSelect)
            ->selectRaw('bank_id, SUM(total_gaji) as total_gaji')
            ->groupBy('bank_id')
            ->get();

        return Inertia::render('Gaji/Index', [
            'gajiList' => $gajiList,
            'bulanSelect' => $bulanSelect,
            'yearSelect' => $yearSelect,
            'gajiPerBank' => $gajiPerBank,
            'filters' => $request->only(['bulan', 'tahun', 'karyawan']),
        ]);
    }

    public function create(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-create']);

        if (auth()->user() && ! auth()->user()->is_superadmin && $karyawan->flag == 2) {
            abort(404);
        }

        $limitTahunan = (int) Setting::getValue('batas_cuti_tahunan', 0);
        $limitSakit = (int) Setting::getValue('batas_cuti_sakit', 0);

        $now = Carbon::now();
        $lastmonth = Carbon::now()->subMonth();

        $gajiData = Gaji::where('karyawan_id', $karyawan->id)
            ->where('bulan', $now->month)
            ->where('tahun', $now->year)
            ->first();

        // Previous month's Cuti accumulation from Gaji
        $gajiKemarin = Gaji::where('karyawan_id', $karyawan->id)
            ->where('bulan', $lastmonth->month)
            ->where('tahun', $lastmonth->year)
            ->first();

        $kemarinTahunan = $gajiKemarin ? (int) $gajiKemarin->cuti_tahunan : 0;
        $kemarinSakit = $gajiKemarin ? (int) $gajiKemarin->cuti_sakit : 0;

        // Current month's actual Cuti count
        $totalCuti = Cuti::where('karyawan_id', $karyawan->id)
            ->whereYear('tgl_mulai', $lastmonth->year) // legacy logic used last month's cuti
            ->whereMonth('tgl_mulai', $lastmonth->month)
            ->selectRaw('SUM(sakit) as total_sakit, SUM(tahunan) as total_tahunan, SUM(mendadak) as total_mendadak')
            ->first();

        $bulaniniSakit = (int) $totalCuti->total_sakit;
        $bulaniniTahunan = (int) $totalCuti->total_tahunan;
        $bulaniniMendadak = (int) $totalCuti->total_mendadak;

        $totalTahunan = $kemarinTahunan + $bulaniniTahunan;
        $totalSakit = $kemarinSakit + $bulaniniSakit;

        $dendaCutiTahunan = 0;
        if ($totalTahunan > $limitTahunan) {
            $dendaCutiTahunan = $kemarinTahunan > $limitTahunan ? $bulaniniTahunan : abs(($limitTahunan - $kemarinTahunan) - $bulaniniTahunan);
        }

        $dendaCutiSakit = 0;
        if ($totalSakit > $limitSakit) {
            $dendaCutiSakit = $kemarinSakit > $limitSakit ? $bulaniniSakit : abs(($limitSakit - $kemarinSakit) - $bulaniniSakit);
        }

        $rupiahDendaTahunan = $karyawan->harian * $dendaCutiTahunan;
        $rupiahDendaSakit = $karyawan->harian * $dendaCutiSakit;
        $rupiahDendaMendadak = $karyawan->harian * $bulaniniMendadak;

        $grandTotalCuti = $bulaniniTahunan + $bulaniniSakit + $bulaniniMendadak;
        $potongPremi = $grandTotalCuti > 0 ? $karyawan->premi : 0;

        $grandTotalDendaCuti = $dendaCutiTahunan + $dendaCutiSakit + $bulaniniMendadak;
        $grandTotalDendaCutiRupiah = $rupiahDendaTahunan + $rupiahDendaSakit + $rupiahDendaMendadak;

        return Inertia::render('Gaji/Create', [
            'karyawan' => $karyawan,
            'now' => ['month' => $now->month, 'year' => $now->year],
            'gajiData' => $gajiData,
            'cutiBulanIni' => [
                'tahunan' => $bulaniniTahunan,
                'sakit' => $bulaniniSakit,
                'mendadak' => $bulaniniMendadak,
            ],
            'dendaCutiTahunan' => $dendaCutiTahunan,
            'dendaCutiSakit' => $dendaCutiSakit,
            'grandTotalCuti' => $grandTotalCuti,
            'potongPremi' => $potongPremi,
            'grandTotalDendaCuti' => $grandTotalDendaCuti,
            'grandTotalDendaCutiRupiah' => $grandTotalDendaCutiRupiah,
        ]);
    }

    public function store(Request $request, Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-create']);

        if (auth()->user() && ! auth()->user()->is_superadmin && $karyawan->flag == 2) {
            abort(404);
        }

        $validated = $request->validate([
            'bulan' => 'required|integer',
            'tahun' => 'required|integer',
            'total_cuti_tahunan' => 'required|numeric',
            'total_cuti_sakit' => 'required|numeric',
            'total_cuti_mendadak' => 'required|numeric',
            'potong_bulanan' => 'required|numeric',
            'potong_premi' => 'required|numeric',
            'bonus' => 'required|numeric',
            'sanksi' => 'required|numeric',
            'privasi' => 'required|integer',
        ]);

        $totalCuti = $validated['total_cuti_tahunan'] + $validated['total_cuti_sakit'] + $validated['total_cuti_mendadak'];
        $totalPotongan = $validated['potong_bulanan'] + $validated['potong_premi'];

        $rupiahHarian = $karyawan->harian * 26;
        $totalGajiHk = $rupiahHarian + $karyawan->bulanan + $karyawan->premi + $validated['bonus'];
        $totalSanksi = $totalPotongan + $validated['sanksi'];
        $gajiAkhir = $totalGajiHk - $totalSanksi;

        Gaji::create([
            'karyawan_id' => $karyawan->id,
            'bulan' => $validated['bulan'],
            'tahun' => $validated['tahun'],
            'bulanan' => $karyawan->bulanan,
            'harian' => $rupiahHarian,
            'premi' => $karyawan->premi,
            'cuti_sakit' => $validated['total_cuti_sakit'],
            'cuti_tahunan' => $validated['total_cuti_tahunan'],
            'cuti_mendadak' => $validated['total_cuti_mendadak'],
            'total_cuti' => $totalCuti,
            'potongan_cuti_bulanan' => $validated['potong_bulanan'],
            'potongan_cuti_premi' => $validated['potong_premi'],
            'total_potongan' => $totalPotongan,
            'bonus' => $validated['bonus'],
            'sanksi' => $validated['sanksi'],
            'total_gaji' => $gajiAkhir,
            'bank_id' => $karyawan->bank_id,
            'flag' => $validated['privasi'],
        ]);

        return redirect()->route('karyawan.show', $karyawan->id)->with('success', 'Gaji '.$karyawan->nama.' created');
    }

    public function destroy(Gaji $gaji)
    {
        Gate::authorize(Karyawan::getPermissions()['gaji-delete']);

        $gaji->delete();

        return redirect()->route('gaji.index')->with('success', 'Gaji deleted');
    }
}
