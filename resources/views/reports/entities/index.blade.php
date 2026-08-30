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

    @include('reports.entities.partials.unassigned-banks')

    @include('reports.entities.partials.entity-table', ['entities' => $activeEntities, 'emptyMessage' => 'No active entities yet. Run `php artisan db:seed --class=ReportingBootstrapSeeder` or add one above.'])

    @if($retiredEntities->isNotEmpty())
    <div class="space-y-2">
        <h3 class="text-sm font-semibold text-gray-500">Retired entities</h3>
        @include('reports.entities.partials.entity-table', ['entities' => $retiredEntities, 'retired' => true, 'emptyMessage' => null])
    </div>
    @endif

    @include('reports.entities.partials.ledger-roles')
    @include('reports.entities.partials.warehouse-fulfillment')
</div>
@endsection
