@extends('layouts.app')

@section('title', 'Reporting Entities')

@section('content')
<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Reporting Entities</h2>
            <p class="mt-1 text-sm text-gray-500">Company units for tax and consolidated reports. Assign banks per entity (PKP / non-PKP).</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('reports.entities.store') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        @csrf
        <p class="mb-3 text-sm font-medium text-gray-900">Add entity</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-xs text-gray-500">Name</label>
                <input type="text" name="name" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="e.g. CV Crystal">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="is_pkp" value="0">
                <input type="checkbox" name="is_pkp" value="1" class="rounded border-gray-300">
                PKP
            </label>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">PKP</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Banks</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($entities as $entity)
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $entity->name }}</td>
                    <td class="px-4 py-3">
                        @if($entity->is_pkp)
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">PKP</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Non-PKP</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $entity->banks_count }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('reports.entities.edit', $entity) }}" class="text-blue-600 hover:underline">Edit</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No entities yet. Run <code class="text-xs">php artisan db:seed --class=ReportingBootstrapSeeder</code> or add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
