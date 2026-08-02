<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CutiController extends Controller
{
    public function create(Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-create']);

        return view('cuti.create', [
            'karyawan' => $karyawan,
        ]);
    }

    public function store(Request $request, Karyawan $karyawan)
    {
        Gate::authorize(Karyawan::getPermissions()['cuti-create']);

        $validated = $request->validate([
            'tgl_mulai' => 'required|date',
            'tgl_akhir' => 'required|date|after_or_equal:tgl_mulai',
            'tipe' => 'required|integer|in:1,2,3',
        ], [], [
            'tgl_mulai' => 'Tanggal Mulai',
            'tgl_akhir' => 'Tanggal Akhir',
            'tipe' => 'Tipe Cuti',
        ]);

        $startDate = Carbon::parse($request->tgl_mulai);
        $endDate = Carbon::parse($request->tgl_akhir);
        $days = $startDate->diffInDays($endDate) + 1;

        $cuti = new Cuti;
        $cuti->karyawan_id = $karyawan->id;
        $cuti->tipe = $request->tipe;
        $cuti->tgl_mulai = $request->tgl_mulai;
        $cuti->tgl_akhir = $request->tgl_akhir;

        if ($request->tipe == 1) {
            $cuti->tahunan = $days;
        } elseif ($request->tipe == 2) {
            $cuti->sakit = $days;
        } elseif ($request->tipe == 3) {
            $cuti->mendadak = $days;
        }

        $cuti->save();

        return redirect()->route('karyawan.show', $karyawan->id)->with('success', 'Cuti created.');
    }
}
