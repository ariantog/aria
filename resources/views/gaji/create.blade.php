@extends('layouts.app')

@section('title', 'Bikin Gaji - ' . $karyawan->nama)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Karyawan', 'href' => route('karyawan.index')],
    ['title' => $karyawan->nama, 'href' => route('karyawan.show', $karyawan->id)],
    ['title' => 'Bikin Gaji', 'href' => '#'],
];
$fmt = fn($v) => format_amount($v ?? 0, 0);
$monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$rupiahHarian = $karyawan->harian * 26;
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('karyawan.show', $karyawan->id) }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">Bikin Gaji: {{ $karyawan->nama }}</h1>
    </div>

    @if($gajiData)
    <div class="space-y-6">
        <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.73 4a2 2 0 00-3.46 0L3.34 16a2 2 0 001.73 3z"/></svg>
            <div>
                <p class="font-bold leading-tight text-amber-900">Perhatian: Data Gaji Ganda</p>
                <p class="mt-1 text-sm text-amber-800">Gaji untuk periode Bulan {{ $gajiData->bulan }}, {{ $gajiData->tahun }} sudah pernah dibuat. Hubungi Admin jika ingin melakukan penyesuaian.</p>
            </div>
        </div>
        <div class="max-w-2xl rounded-xl border border-emerald-200 bg-emerald-50/30 shadow-sm">
            <div class="border-b border-emerald-100 px-6 py-4"><h2 class="font-semibold text-emerald-900">Rincian Gaji Tersimpan</h2></div>
            <div class="space-y-6 p-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><p class="text-gray-500">Gaji Pokok &amp; Harian</p><p class="font-semibold">{{ $fmt((float)$gajiData->bulanan + (float)$gajiData->harian) }}</p></div>
                    <div><p class="text-gray-500">Premi / Tunjangan</p><p class="font-semibold">{{ $fmt($gajiData->premi) }}</p></div>
                    <div><p class="text-gray-500">Bonus / Insentif</p><p class="font-semibold text-emerald-600">+{{ $fmt($gajiData->bonus) }}</p></div>
                    <div><p class="text-gray-500">Total Potongan (Cuti/Premi/Sanksi)</p><p class="font-semibold text-red-600">-{{ $fmt((float)$gajiData->total_potongan + (float)$gajiData->sanksi) }}</p></div>
                </div>
                <div class="flex items-center justify-between border-t pt-4">
                    <span class="text-lg font-bold">Total Gaji Akhir</span>
                    <span class="text-2xl font-black text-emerald-700">Rp {{ $fmt($gajiData->total_gaji) }}</span>
                </div>
                <div class="border-t pt-4">
                    <a href="{{ route('karyawan.show', $karyawan->id) }}" class="block w-full rounded-md border border-gray-300 py-2 text-center text-sm hover:bg-gray-50">Kembali ke Profil</a>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3"
         x-data="{
            harian: {{ (int)$rupiahHarian }},
            bulanan: {{ (int)$karyawan->bulanan }},
            premi: {{ (int)$karyawan->premi }},
            potongBulanan: {{ (int)$grandTotalDendaCutiRupiah }},
            potongPremi: {{ (int)$potongPremi }},
            bonus: 0,
            sanksi: 0,
            get gajiAkhir() {
                const hk = this.harian + this.bulanan + this.premi + Number(this.bonus);
                const pot = Number(this.potongBulanan) + Number(this.potongPremi) + Number(this.sanksi);
                return hk - pot;
            },
            fmt(v) { return formatAmountId(v); },
         }">
        {{-- Kalkulasi Sistem --}}
        <div class="rounded-xl border border-blue-200 bg-blue-50/50 shadow-sm md:col-span-1">
            <div class="px-6 pt-6 pb-4"><h2 class="text-lg font-semibold text-zinc-900">Kalkulasi Sistem</h2></div>
            <div class="space-y-4 px-6 pb-6 text-sm">
                <div>
                    <h4 class="mb-2 font-medium">Rincian Gaji</h4>
                    <div class="space-y-1">
                        <div class="flex justify-between"><span class="text-gray-500">Bulanan</span><span class="font-semibold">{{ $fmt($karyawan->bulanan) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Harian x 26 Hari</span><span class="font-semibold">{{ $fmt($rupiahHarian) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Premi</span><span class="font-semibold">{{ $fmt($karyawan->premi) }}</span></div>
                    </div>
                </div>
                <div class="mt-2 border-t border-blue-200 pt-4">
                    <h4 class="mb-2 font-medium">Rincian Cuti (Bulan Ini)</h4>
                    <div class="space-y-1">
                        <div class="flex justify-between"><span class="text-gray-500">Tahunan</span><span>{{ $cutiBulanIni['tahunan'] }} Hari</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Sakit</span><span>{{ $cutiBulanIni['sakit'] }} Hari</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Mendadak</span><span>{{ $cutiBulanIni['mendadak'] }} Hari</span></div>
                    </div>
                </div>
                <div class="mt-2 border-t border-red-100 pt-4">
                    <h4 class="mb-2 font-medium text-red-600">Cuti Melewati Batas Tahunan</h4>
                    <div class="space-y-1">
                        <div class="flex justify-between"><span class="text-gray-500">Tahunan</span><span class="font-medium text-red-600">{{ $dendaCutiTahunan }} Hari (Denda)</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Sakit</span><span class="font-medium text-red-600">{{ $dendaCutiSakit }} Hari (Denda)</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Mendadak</span><span class="font-medium text-red-600">{{ $cutiBulanIni['mendadak'] }} Hari (Potong)</span></div>
                    </div>
                </div>
                <div class="mt-2 border-t border-blue-200 pt-4">
                    <div class="flex items-center justify-between rounded bg-blue-100 p-2">
                        <span class="font-bold text-blue-900">Total Take Home Pay</span>
                        <span class="text-lg font-bold text-blue-700" x-text="fmt(gajiAkhir)"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Input --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm md:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Data Penggajian</h2></div>
            <div class="p-6">
                <form method="POST" action="{{ route('karyawan.gaji.store', $karyawan->id) }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="bulan" value="{{ $now['month'] }}">
                    <input type="hidden" name="tahun" value="{{ $now['year'] }}">
                    <input type="hidden" name="total_cuti_tahunan" value="{{ $cutiBulanIni['tahunan'] }}">
                    <input type="hidden" name="total_cuti_sakit" value="{{ $cutiBulanIni['sakit'] }}">
                    <input type="hidden" name="total_cuti_mendadak" value="{{ $cutiBulanIni['mendadak'] }}">
                    <input type="hidden" name="potong_bulanan" value="{{ (int)$grandTotalDendaCutiRupiah }}">
                    <input type="hidden" name="potong_premi" value="{{ (int)$potongPremi }}">

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h3 class="mb-1 text-sm font-medium text-gray-500">Periode Penggajian</h3>
                        <p class="text-xl font-bold tracking-tight">{{ $monthNames[(int)$now['month'] - 1] }} {{ $now['year'] }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 border-t pt-4 sm:grid-cols-2">
                        <div class="space-y-4">
                            <h4 class="font-medium">Tambahan</h4>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Bonus / Insentif (Rp)</label>
                                <input type="number" name="bonus" x-model.number="bonus" value="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                                @error('bonus')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-medium text-red-600">Potongan &amp; Sanksi</h4>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Sanksi Lainnya (Rp)</label>
                                <input type="number" name="sanksi" x-model.number="sanksi" value="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                                @error('sanksi')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 border-t pt-4">
                        <label class="text-sm font-medium">Status Publikasi</label>
                        <select name="privasi" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="1" @selected(old('privasi','1')==='1')>Publik</option>
                            <option value="2" @selected(old('privasi')==='2')>Private (Rahasia)</option>
                        </select>
                        @error('privasi')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <a href="{{ route('karyawan.show', $karyawan->id) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</a>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan Data Gaji</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
