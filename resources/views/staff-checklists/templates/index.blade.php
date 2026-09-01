@extends('layouts.app')

@section('title', 'Template Checklist')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Checklist Peran', 'href' => route('staff-checklists.index')],
    ['title' => 'Template Checklist', 'href' => route('staff-checklists.templates.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Template Checklist</h2>
            <p class="mt-0.5 text-sm text-gray-500">Edit atau hapus item checklist per peran operasional.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('staff-checklists.index') }}"
               class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Kembali ke ringkasan
            </a>
            @if($can['edit'] ?? false)
            <a href="{{ route('staff-checklists.templates.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
               data-testid="create-checklist-template">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah template
            </a>
            @endif
        </div>
    </div>

    @forelse($roles as $role)
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="template-role-{{ $role->slug }}">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-base font-semibold text-gray-900">{{ $role->name }}</h3>
            <p class="text-sm text-gray-500">{{ $role->checklistTemplates->count() }} item</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Item</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Frekuensi</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Halaman</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        @if(($can['edit'] ?? false) || ($can['delete'] ?? false))
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($role->checklistTemplates as $template)
                    <tr class="hover:bg-gray-50" data-testid="template-row-{{ $template->id }}">
                        <td class="px-5 py-3">
                            <div class="font-medium text-gray-900">{{ $template->title }}</div>
                            @if($template->description)
                            <div class="mt-0.5 text-xs text-gray-500">{{ $template->description }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $template->frequency->label() }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            @if($template->route_name)
                                <span class="font-mono text-xs">{{ $template->route_name }}</span>
                                @if($template->route_query)
                                <span class="text-xs text-gray-400">?{{ $template->route_query }}</span>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if($template->is_active)
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Aktif</span>
                            @else
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        @if(($can['edit'] ?? false) || ($can['delete'] ?? false))
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                @if($can['edit'] ?? false)
                                <a href="{{ route('staff-checklists.templates.edit', $template) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-900"
                                   title="Edit"
                                   data-testid="edit-template-{{ $template->id }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                                @if($can['delete'] ?? false)
                                <form method="POST" action="{{ route('staff-checklists.templates.destroy', $template) }}"
                                      onsubmit="return confirm('Hapus template ini? Penyelesaian terkait akan ikut dihapus.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600"
                                            title="Hapus"
                                            data-testid="delete-template-{{ $template->id }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-6 text-sm text-gray-500">Belum ada template untuk peran ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div class="rounded-xl border border-gray-200 bg-white px-5 py-8 text-sm text-gray-500">Belum ada peran operasional.</div>
    @endforelse
</div>
@endsection
