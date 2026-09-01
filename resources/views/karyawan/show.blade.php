@extends('layouts.app')

@section('title', 'Detail Karyawan - ' . $karyawan->nama)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Karyawan', 'href' => route('karyawan.index')],
    ['title' => $karyawan->nama, 'href' => route('karyawan.show', $karyawan->id)],
];
$user = auth()->user();
$isSuper = $user && $user->is_superadmin;
$canEditGaji = $user && ($isSuper || $user->can('karyawan-gaji-edit'));
$fmt = fn($v) => format_amount($v ?? 0, 0);
$canEditCuti = $user && ($isSuper || $user->can('karyawan-cuti-edit'));
$canDeleteCuti = $user && ($isSuper || $user->can('karyawan-cuti-delete'));
$tipeCuti = \App\Models\Cuti::$typeStyles;
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('karyawan.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold">{{ $karyawan->nama }}</h1>
                <p class="text-gray-500">{{ $karyawan->no_telp }}</p>
                @if($karyawan->nama_absensi)
                <p class="text-sm text-gray-500">Nama absensi: <span class="font-medium text-gray-700">{{ $karyawan->nama_absensi }}</span></p>
                @endif
                @if($karyawan->absen_id)
                <p class="text-sm text-gray-500">ID absensi: <span class="font-medium text-gray-700">{{ $karyawan->absen_id }}</span></p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($isSuper || ($user->can('karyawan-edit') && $karyawan->flag != 2))
            <a href="{{ route('karyawan.edit', $karyawan->id) }}" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Edit Profil</a>
            @endif
            @if($isSuper || ($user->can('karyawan-gaji-create') && ($isSuper || $karyawan->flag != 2)))
            <a href="{{ route('karyawan.gaji.create', $karyawan->id) }}" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Buat Gaji</a>
            @endif
            @if($isSuper || ($user->can('karyawan-cuti-create') && ($isSuper || $karyawan->flag != 2)))
            <a href="{{ route('karyawan.cuti.create', $karyawan->id) }}" class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200">Tambah Cuti</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm md:col-span-1">
            <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Profil Karyawan</h2></div>
            <div class="space-y-4 p-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Alamat</h4>
                    <p class="mt-1 leading-relaxed">{{ $karyawan->alamat ?: '-' }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                    <div><h4 class="text-sm font-medium text-gray-500">Bank</h4><p class="mt-1 font-medium">{{ $karyawan->bank->name ?? 'Kas Tunai' }}</p></div>
                    <div><h4 class="text-sm font-medium text-gray-500">Privasi</h4><p class="mt-1 font-medium">{{ $karyawan->flag == 1 ? 'Publik' : 'Privasi' }}</p></div>
                </div>
                <div class="space-y-2 border-t border-gray-100 pt-4">
                    <div class="flex justify-between"><span class="text-sm text-gray-500">Gaji Bulanan</span><span class="font-medium">{{ $fmt($karyawan->bulanan) }}</span></div>
                    <div class="flex justify-between"><span class="text-sm text-gray-500">Tarif Harian</span><span class="font-medium">{{ $fmt($karyawan->harian) }}</span></div>
                    <div class="flex justify-between"><span class="text-sm text-gray-500">Jam Kerja / Hari</span><span class="font-medium">{{ (int) ($karyawan->jam_kerja ?: 8) }} jam</span></div>
                    <div class="flex justify-between"><span class="text-sm text-gray-500">Jam Masuk</span><span class="font-medium">{{ ($karyawan->waktu_dibatasi ?? true) ? ($karyawan->jam_masuk ?? '08:00') : 'Fleksibel' }}</span></div>
                </div>
            </div>
        </div>

        <div class="space-y-6 md:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="font-semibold">Riwayat Gaji</h2>
                    <p class="text-sm text-gray-500">Gaji bulan-bulan terakhir</p>
                </div>
                <div class="max-h-[300px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100">
                            <tr class="border-b">
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Periode</th>
                                <th class="h-10 px-4 text-right font-medium text-gray-500">Total Gaji</th>
                                <th class="h-10 px-4 text-center font-medium text-gray-500">Cuti (T/S/M)</th>
                                @if($canEditGaji)<th class="h-10 px-4 text-right font-medium text-gray-500"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($karyawan->gaji as $gaji)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4 font-medium">Bulan {{ $gaji->bulan }} / {{ $gaji->tahun }}</td>
                                <td class="p-4 text-right font-bold text-green-700">{{ $fmt($gaji->total_gaji) }}</td>
                                <td class="p-4 text-center text-gray-500">{{ (int)($gaji->cuti_tahunan ?? 0) }} / {{ (int)($gaji->cuti_sakit ?? 0) }} / {{ (int)($gaji->cuti_mendadak ?? 0) }}</td>
                                @if($canEditGaji)
                                <td class="p-4 text-right">
                                    <a href="{{ route('gaji.edit', $gaji) }}" class="text-sm font-medium text-blue-600 hover:underline">Edit</a>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr><td colspan="{{ $canEditGaji ? 4 : 3 }}" class="p-4 text-center text-gray-500">Belum ada histori gaji.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="font-semibold">Riwayat Cuti</h2>
                    <p class="text-sm text-gray-500">Cuti yang pernah diambil</p>
                </div>
                <div class="max-h-[300px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100">
                            <tr class="border-b">
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Tanggal</th>
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Tipe</th>
                                <th class="h-10 px-4 text-center font-medium text-gray-500">Lama</th>
                                @if($canEditCuti || $canDeleteCuti)
                                <th class="h-10 px-4 text-right font-medium text-gray-500"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($karyawan->cuti as $cuti)
                            @php [$label, $color] = $tipeCuti[$cuti->tipe] ?? ['Lainnya', 'text-gray-600']; @endphp
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4">
                                    {{ \Carbon\Carbon::parse($cuti->tgl_mulai)->translatedFormat('d M Y') }}
                                    @if($cuti->tgl_mulai != $cuti->tgl_akhir) - {{ \Carbon\Carbon::parse($cuti->tgl_akhir)->translatedFormat('d M Y') }}@endif
                                </td>
                                <td class="p-4 font-medium {{ $color }}">{{ $label }}</td>
                                <td class="p-4 text-center">{{ $cuti->total_cuti }} Hari</td>
                                @if($canEditCuti || $canDeleteCuti)
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if($canEditCuti)
                                        <a href="{{ route('cuti.edit', $cuti) }}" class="text-sm font-medium text-blue-600 hover:underline">Edit</a>
                                        @endif
                                        @if($canDeleteCuti)
                                        <form method="POST" action="{{ route('cuti.destroy', $cuti) }}" onsubmit="return confirm('Hapus catatan cuti ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Hapus</button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr><td colspan="{{ ($canEditCuti || $canDeleteCuti) ? 4 : 3 }}" class="p-4 text-center text-gray-500">Belum ada catatan cuti.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="font-semibold">Absensi terbaru</h2>
                    <p class="text-sm text-gray-500">Jam kerja = jam pulang − jam masuk. Keterlambatan tidak dihitung.</p>
                </div>
                <div class="max-h-[300px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100">
                            <tr class="border-b">
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Tanggal</th>
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Masuk</th>
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Pulang</th>
                                <th class="h-10 px-4 text-right font-medium text-gray-500">Jam</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($karyawan->absensiHari as $hari)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4">{{ $hari->tanggal->translatedFormat('d M Y') }}</td>
                                <td class="p-4">{{ $hari->masuk ?: '—' }}</td>
                                <td class="p-4">{{ $hari->pulang ?: '—' }}</td>
                                <td class="p-4 text-right {{ $hari->incomplete ? 'text-amber-700' : '' }}">
                                    {{ number_format((float) $hari->jam, 2) }}
                                    @if($hari->incomplete)<span class="ml-1 text-xs">tidak lengkap</span>@endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="p-4 text-center text-gray-500">Belum ada absensi. Unggah file fingerprint di menu Absensi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
