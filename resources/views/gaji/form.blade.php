@extends('layouts.app')

@php
use App\Support\KaryawanVisibility;
@endphp

@section('title', ($mode === 'edit' ? 'Edit' : 'Buat').' Gaji - '.$karyawan->nama)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Karyawan', 'href' => route('karyawan.index')],
    ['title' => $karyawan->nama, 'href' => route('karyawan.show', $karyawan->id)],
    ['title' => ($mode === 'edit' ? 'Edit' : 'Buat').' Gaji', 'href' => '#'],
];
$monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$user = auth()->user();
$isSuper = $user && $user->is_superadmin;
$harianRateDefault = $gaji ? (int) round($gaji->harian / 26) : (int) $calculation['harian_rate'];
$jamKerja = (int) $calculation['jam_kerja_per_hari'];
$lemburMultiplier = (float) $calculation['lembur_multiplier'];

$defaults = [
    'bulan' => old('bulan', $gaji->bulan ?? $calculation['bulan']),
    'tahun' => old('tahun', $gaji->tahun ?? $calculation['tahun']),
    'bulanan' => old('bulanan', $gaji->bulanan ?? $calculation['bulanan']),
    'harian_rate' => old('harian_rate', $harianRateDefault),
    'total_cuti_tahunan' => old('total_cuti_tahunan', $gaji->cuti_tahunan ?? $calculation['cuti_tahunan']),
    'total_cuti_sakit' => old('total_cuti_sakit', $gaji->cuti_sakit ?? $calculation['cuti_sakit']),
    'total_cuti_mendadak' => old('total_cuti_mendadak', $gaji->cuti_mendadak ?? $calculation['cuti_mendadak']),
    'hari_izin' => old('hari_izin', $gaji->hari_izin ?? $calculation['hari_izin']),
    'potong_harian' => old('potong_harian', $gaji->potongan_harian ?? $calculation['potongan_harian']),
    'menit_telat' => old('menit_telat', $gaji->menit_telat ?? $calculation['menit_telat']),
    'potong_telat' => old('potong_telat', $gaji->potongan_telat ?? $calculation['potongan_telat']),
    'jam_lembur' => old('jam_lembur', $gaji->jam_lembur ?? $calculation['jam_lembur']),
    'upah_lembur' => old('upah_lembur', $gaji->upah_lembur ?? $calculation['upah_lembur']),
    'bonus' => old('bonus', $gaji->bonus ?? 0),
    'sanksi' => old('sanksi', $gaji->sanksi ?? 0),
    'privasi' => old('privasi', $gaji->flag ?? KaryawanVisibility::FLAG_PUBLIC),
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('karyawan.show', $karyawan->id) }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">{{ $mode === 'edit' ? 'Edit' : 'Buat' }} Gaji: {{ $karyawan->nama }}</h1>
            <p class="text-sm text-gray-500">Formula: bulanan + (26 × harian) + bonus + lembur − potongan harian − potongan telat − sanksi. Semua angka bisa disesuaikan untuk sanggahan.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3"
         x-data="{
            bulanan: {{ (int) $defaults['bulanan'] }},
            harianRate: {{ (int) $defaults['harian_rate'] }},
            potongHarian: {{ (int) $defaults['potong_harian'] }},
            potongTelat: {{ (int) $defaults['potong_telat'] }},
            upahLembur: {{ (int) $defaults['upah_lembur'] }},
            bonus: {{ (int) $defaults['bonus'] }},
            sanksi: {{ (int) $defaults['sanksi'] }},
            jamLembur: {{ (float) $defaults['jam_lembur'] }},
            jamKerja: {{ $jamKerja }},
            lemburMultiplier: {{ $lemburMultiplier }},
            workingDays: {{ App\Services\Payroll\GajiSalaryCalculator::WORKING_DAYS_PER_MONTH }},
            get harianTotal() { return this.harianRate * this.workingDays; },
            syncUpahLembur() {
                if (this.harianRate <= 0 || this.jamLembur <= 0) {
                    this.upahLembur = 0;
                    return;
                }
                const hourly = this.harianRate / this.jamKerja;
                this.upahLembur = Math.round(this.jamLembur * hourly * this.lemburMultiplier);
            },
            get gajiAkhir() {
                const hk = Number(this.bulanan) + this.harianTotal + Number(this.bonus) + Number(this.upahLembur);
                const pot = Number(this.potongHarian) + Number(this.potongTelat) + Number(this.sanksi);
                return hk - pot;
            },
            fmt(v) { return formatAmountId(v); },
         }">
        <div class="rounded-xl border border-blue-200 bg-blue-50/50 shadow-sm md:col-span-1">
            <div class="px-6 pt-6 pb-4"><h2 class="text-lg font-semibold text-gray-900">Kalkulasi Sistem</h2></div>
            <div class="space-y-4 px-6 pb-6 text-sm">
                <div>
                    <h4 class="mb-2 font-medium">Rincian Upah</h4>
                    <div class="space-y-1">
                        <div class="flex justify-between"><span class="text-gray-500">Bulanan</span><span class="font-semibold" x-text="fmt(bulanan)"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Harian × 26</span><span class="font-semibold" x-text="fmt(harianTotal)"></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Lembur</span><span class="font-semibold" x-text="fmt(upahLembur)"></span></div>
                    </div>
                </div>
                <div class="mt-2 border-t border-blue-200 pt-4">
                    <h4 class="mb-2 font-medium">Cuti (bulan {{ $monthNames[$calculation['bulan'] - 1] }} {{ $calculation['tahun'] }})</h4>
                    <div class="space-y-1 text-gray-600">
                        <div class="flex justify-between"><span>Tahunan</span><span>{{ $calculation['cuti_tahunan'] }} hari</span></div>
                        <div class="flex justify-between"><span>Sakit</span><span>{{ $calculation['cuti_sakit'] }} hari</span></div>
                        <div class="flex justify-between"><span>Mendadak</span><span>{{ $calculation['cuti_mendadak'] }} hari</span></div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Cuti tahunan & sakit dalam kuota tidak kena potong harian. Batas tahunan: {{ $calculation['limit_tahunan'] }} hari · sakit: {{ $calculation['limit_sakit'] }} hari.</p>
                </div>
                <div class="mt-2 border-t border-red-100 pt-4">
                    <h4 class="mb-2 font-medium text-red-600">Potongan otomatis</h4>
                    <div class="space-y-1">
                        <div class="flex justify-between"><span class="text-gray-500">Denda tahunan</span><span>{{ $calculation['denda_cuti_tahunan'] }} hari</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Denda sakit</span><span>{{ $calculation['denda_cuti_sakit'] }} hari</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Mendadak + izin</span><span>potong harian</span></div>
                        @if($karyawan->waktu_dibatasi ?? true)
                        <div class="flex justify-between"><span class="text-gray-500">Grace telat</span><span>{{ $calculation['grace_period_menit'] }} menit</span></div>
                        @else
                        <p class="text-xs text-gray-500">Karyawan ini tidak dibatasi waktu — potong telat default 0.</p>
                        @endif
                    </div>
                </div>
                <div class="mt-2 border-t border-blue-200 pt-4">
                    <div class="flex items-center justify-between rounded bg-blue-100 p-2">
                        <span class="font-bold text-blue-900">Total Gaji</span>
                        <span class="text-lg font-bold text-blue-700" x-text="fmt(gajiAkhir)"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm md:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Data Penggajian</h2></div>
            <div class="p-6">
                <form method="POST"
                      action="{{ $mode === 'edit' ? route('gaji.update', $gaji) : route('karyawan.gaji.store', $karyawan) }}"
                      class="space-y-6">
                    @csrf
                    @if($mode === 'edit') @method('PUT') @endif

                    <div class="grid grid-cols-2 gap-4">
                        @if($mode === 'edit')
                        <input type="hidden" name="bulan" value="{{ $defaults['bulan'] }}">
                        <input type="hidden" name="tahun" value="{{ $defaults['tahun'] }}">
                        @endif
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Bulan</label>
                            <select class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" @if($mode === 'edit') disabled @else name="bulan" @endif>
                                @foreach($monthNames as $i => $m)
                                <option value="{{ $i + 1 }}" @selected((int)$defaults['bulan'] === $i + 1)>{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Tahun</label>
                            <input type="number" value="{{ $defaults['tahun'] }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" @if($mode === 'edit') disabled @else name="tahun" @endif>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Gaji Bulanan (Rp)</label>
                            <input type="number" name="bulanan" x-model.number="bulanan" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Tarif Harian per Hari (Rp)</label>
                            <input type="number" name="harian_rate" x-model.number="harianRate" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <p class="text-xs text-gray-500">Disimpan sebagai × 26 hari kerja.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Cuti tahunan (hari)</label>
                            <input type="number" name="total_cuti_tahunan" value="{{ $defaults['total_cuti_tahunan'] }}" min="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Cuti sakit (hari)</label>
                            <input type="number" name="total_cuti_sakit" value="{{ $defaults['total_cuti_sakit'] }}" min="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Cuti mendadak (hari)</label>
                            <input type="number" name="total_cuti_mendadak" value="{{ $defaults['total_cuti_mendadak'] }}" min="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Hari izin (potong harian)</label>
                            <input type="number" name="hari_izin" value="{{ $defaults['hari_izin'] }}" min="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <p class="text-xs text-gray-500">Izin disetujui tetap kena potong tarif harian.</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Potongan harian (Rp)</label>
                            <input type="number" name="potong_harian" x-model.number="potongHarian" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Menit telat (bulan ini)</label>
                            <input type="number" name="menit_telat" value="{{ $defaults['menit_telat'] }}" min="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Potongan telat (Rp)</label>
                            <input type="number" name="potong_telat" x-model.number="potongTelat" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <p class="text-xs text-gray-500">Per jam setelah grace {{ $calculation['grace_period_menit'] }} menit × (harian ÷ {{ $jamKerja }}).</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Jam lembur</label>
                            <input type="number" name="jam_lembur" step="0.5" min="0" x-model.number="jamLembur" @input="syncUpahLembur()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Upah lembur (Rp)</label>
                            <input type="number" name="upah_lembur" x-model.number="upahLembur" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <p class="text-xs text-gray-500">Tarif: harian ÷ {{ $jamKerja }} × {{ $lemburMultiplier }}.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Bonus (Rp)</label>
                            <input type="number" name="bonus" x-model.number="bonus" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Sanksi (Rp)</label>
                            <input type="number" name="sanksi" x-model.number="sanksi" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="space-y-2 border-t pt-4">
                        <label class="text-sm font-medium">Visibilitas gaji</label>
                        @if($isSuper)
                        <select name="privasi" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="{{ KaryawanVisibility::FLAG_PUBLIC }}" @selected((int)$defaults['privasi'] === KaryawanVisibility::FLAG_PUBLIC)>Publik (role payroll)</option>
                            <option value="{{ KaryawanVisibility::FLAG_PRIVATE }}" @selected((int)$defaults['privasi'] === KaryawanVisibility::FLAG_PRIVATE)>Privasi (hanya superadmin)</option>
                        </select>
                        @else
                        <input type="hidden" name="privasi" value="{{ KaryawanVisibility::FLAG_PUBLIC }}">
                        <p class="text-sm text-gray-600">Publik — hanya superadmin yang bisa membuat gaji privasi.</p>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <a href="{{ route('karyawan.show', $karyawan->id) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</a>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan Gaji</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
