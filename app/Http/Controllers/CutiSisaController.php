<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Services\Payroll\CutiSisaService;
use App\Support\KaryawanVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CutiSisaController extends Controller
{
    public function __construct(
        protected CutiSisaService $sisa,
    ) {}

    public function update(Request $request, Karyawan $karyawan)
    {
        $this->authorizeEdit($request, $karyawan);

        $validated = $request->validate([
            'tahun' => 'nullable|integer|min:2000|max:2100',
            'sisa_tahunan' => 'required|integer|min:0|max:366',
            'sisa_sakit' => 'required|integer|min:0|max:366',
            'catatan' => 'nullable|string|max:255',
        ], [], [
            'sisa_tahunan' => 'sisa cuti tahunan',
            'sisa_sakit' => 'sisa cuti sakit',
            'catatan' => 'catatan',
        ]);

        $year = (int) ($validated['tahun'] ?? now()->year);

        $this->sisa->setManual(
            $karyawan,
            $year,
            (int) $validated['sisa_tahunan'],
            (int) $validated['sisa_sakit'],
            $request->user(),
            filled($validated['catatan'] ?? null) ? trim((string) $validated['catatan']) : null,
        );

        $redirect = $request->input('redirect', 'show');

        if ($redirect === 'index') {
            return redirect()
                ->route('karyawan.index')
                ->with('success', 'Sisa cuti '.$karyawan->nama.' diperbarui.');
        }

        return redirect()
            ->route('karyawan.show', $karyawan)
            ->with('success', 'Sisa cuti diperbarui.');
    }

    private function authorizeEdit(Request $request, Karyawan $karyawan): void
    {
        $user = $request->user();
        $can = $user && (
            $user->can(Karyawan::getPermissions()['cuti-edit'])
            || $user->can(Karyawan::getPermissions()['edit'])
        );

        if (! $can) {
            Gate::authorize(Karyawan::getPermissions()['cuti-edit']);
        }

        if (! KaryawanVisibility::canViewKaryawanRecord($user, $karyawan)) {
            abort(404);
        }
    }
}
