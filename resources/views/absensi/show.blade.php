@extends('layouts.app')

@section('title', 'Hasil Impor Absensi')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Absensi', 'href' => route('absensi.index')],
    ['title' => $import->filename, 'href' => '#'],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('absensi.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">{{ $import->filename }}</h1>
            <p class="text-sm text-gray-500">
                {{ $import->period_start->translatedFormat('d M Y') }} – {{ $import->period_end->translatedFormat('d M Y') }}
                · {{ $import->matched_count }} cocok · {{ $import->unmatched_count }} belum terhubung
            </p>
        </div>
    </div>

    @if($unmatched->isNotEmpty())
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="absensi-unmatched">
        <p class="font-medium">ID mesin belum terhubung ke karyawan:</p>
        <p class="mt-1">{{ $unmatched->pluck('absen_id')->join(', ') }}</p>
        <p class="mt-1 text-xs">Isi kolom ID Absensi di data karyawan (huruf besar/kecil tidak penting), lalu unggah ulang file yang sama — baris tanggal itu akan diganti.</p>
    </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">ID mesin</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Karyawan</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total jam</th>
                        @foreach($dates as $date)
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-500">{{ \Carbon\Carbon::parse($date)->format('d') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($employees as $row)
                    <tr class="hover:bg-gray-50" data-testid="absensi-emp-{{ $row['absen_id'] }}">
                        <td class="px-4 py-2 font-mono text-xs">{{ $row['absen_id'] }}</td>
                        <td class="px-4 py-2">
                            @if($row['karyawan'])
                            <a href="{{ route('karyawan.show', $row['karyawan']) }}" class="font-medium text-blue-600 hover:underline">{{ $row['karyawan']->nama }}</a>
                            @else
                            <span class="text-amber-700">{{ $row['nama_mesin'] ?: 'Belum terhubung' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-medium">{{ number_format($row['jam_total'], 2) }}</td>
                        @foreach($dates as $date)
                        @php $day = $row['days']->first(fn ($d) => $d->tanggal->toDateString() === $date); @endphp
                        <td class="px-3 py-2 text-center text-xs {{ ($day && $day->incomplete) ? 'text-amber-700' : 'text-gray-700' }}">
                            @if($day && ($day->masuk || $day->pulang || $day->jam > 0))
                                {{ $day->masuk ?: '—' }}@if($day->pulang)<br>{{ $day->pulang }}@endif
                                <div class="text-[10px] text-gray-500">{{ number_format((float) $day->jam, 2) }}j</div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
