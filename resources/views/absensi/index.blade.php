@extends('layouts.app')

@section('title', 'Absensi Fingerprint')

@section('content')
@php
$breadcrumbs = [['title' => 'Absensi', 'href' => route('absensi.index')]];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Absensi Fingerprint</h1>
            <p class="text-gray-500">Unggah file Excel mesin absensi (sheet Lap. Log Absen). Jam kerja dihitung dari punch pertama ke punch terakhir; keterlambatan diabaikan.</p>
        </div>
        @if($canImport ?? false)
        <a href="{{ route('absensi.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" data-testid="absensi-import-link">
            Unggah Absensi
        </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">File</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Periode</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-500">Cocok / Belum</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-500">Baris hari</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Diunggah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($imports as $import)
                    <tr class="hover:bg-gray-50" data-testid="absensi-import-{{ $import->id }}">
                        <td class="px-5 py-3">
                            <a href="{{ route('absensi.show', $import) }}" class="font-medium text-blue-600 hover:underline">{{ $import->filename }}</a>
                        </td>
                        <td class="px-5 py-3 text-gray-700">
                            {{ $import->period_start->translatedFormat('d M Y') }}
                            – {{ $import->period_end->translatedFormat('d M Y') }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="text-green-700">{{ $import->matched_count }}</span>
                            /
                            <span class="{{ $import->unmatched_count ? 'text-amber-700' : 'text-gray-500' }}">{{ $import->unmatched_count }}</span>
                        </td>
                        <td class="px-5 py-3 text-center">{{ $import->day_count }}</td>
                        <td class="px-5 py-3 text-gray-500">
                            {{ $import->created_at?->translatedFormat('d M Y H:i') }}
                            @if($import->user)
                            <div class="text-xs">{{ $import->user->name ?? $import->user->username }}</div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada file absensi. Hubungkan ID mesin di data karyawan (kolom ID Absensi), lalu unggah Excel.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $imports, 'label' => 'impor'])
    </div>
</div>
@endsection
