@extends('layouts.app')

@section('title', 'Upload Faktur Pajak')

@section('content')
<div class="mx-auto max-w-xl p-4">
    <div class="mb-4">
        <a href="{{ route('reports.tax.faktur.index') }}" class="text-sm text-blue-600 hover:underline">← Kembali ke daftar faktur</a>
        <h2 class="mt-2 text-2xl font-bold text-gray-900">Upload Faktur Pajak PDF</h2>
        <p class="mt-1 text-sm text-gray-500">MDS / e-faktur format. Sistem akan membaca DPP, PPN, dan NPWP penjual/pembeli.</p>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('reports.tax.faktur.parse') }}" enctype="multipart/form-data"
          class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <label class="mb-2 block text-sm font-medium text-gray-700" for="pdf">File PDF</label>
        <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" required
               class="w-full text-sm" data-testid="faktur-pdf-input">
        @error('pdf')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <button type="submit" class="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Parse & review
        </button>
    </form>
</div>
@endsection
