@extends('layouts.app')

@section('title', 'Tambah Template Checklist')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Checklist Peran', 'href' => route('staff-checklists.index')],
    ['title' => 'Template Checklist', 'href' => route('staff-checklists.templates.index')],
    ['title' => 'Tambah', 'href' => route('staff-checklists.templates.create')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Tambah Template Checklist</h2>
        <p class="mt-0.5 text-sm text-gray-500">Item baru akan muncul di checklist pengguna yang punya peran terkait.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('staff-checklists.templates.store') }}" class="p-6" data-testid="checklist-template-form">
            @csrf
            @include('staff-checklists.templates.partials.form')
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('staff-checklists.templates.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection
