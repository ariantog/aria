@extends('layouts.app')

@section('title', 'Edit Template Checklist')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Checklist Peran', 'href' => route('staff-checklists.index')],
    ['title' => 'Template Checklist', 'href' => route('staff-checklists.templates.index')],
    ['title' => 'Edit', 'href' => route('staff-checklists.templates.edit', $template)],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Edit Template Checklist</h2>
        <p class="mt-0.5 text-sm text-gray-500">Perubahan judul atau frekuensi berlaku untuk periode berjalan ke depan.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('staff-checklists.templates.update', $template) }}" class="p-6" data-testid="checklist-template-form">
            @csrf
            @method('PUT')
            @include('staff-checklists.templates.partials.form', ['template' => $template])
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('staff-checklists.templates.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Simpan perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
