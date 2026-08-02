@extends('layouts.app')

@section('title', 'Permissions')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Permissions', 'href' => route('permissions.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Permissions</h2>
            <p class="mt-0.5 text-sm text-gray-500">View and generate system permissions.</p>
        </div>
        @if($can['generate'])
        <form method="POST" action="{{ route('permissions.generate') }}" class="flex items-end gap-2"
              onsubmit="return confirm('Generate/update permissions now?')">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Module (optional)</label>
                <input type="text" name="module_name" value="{{ old('module_name') }}" placeholder="e.g. Item"
                       class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Generate
            </button>
        </form>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Permission</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Guard</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Created At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($permissions as $permission)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4">
                            <code class="rounded border border-gray-200 bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-700">{{ $permission->name }}</code>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $permission->guard_name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $permission->created_at?->format('d/m/Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500">No permissions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $permissions, 'label' => 'permissions'])
    </div>
</div>
@endsection
