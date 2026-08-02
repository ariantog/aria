@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Dashboard</h2>
        <p class="mt-0.5 text-sm text-gray-500">Welcome back, {{ auth()->user()->name }}.</p>
    </div>

    {{-- Quick-links grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('transactions.index') }}"
           class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-blue-300 hover:shadow-md transition-all">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50">
                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Transactions</p>
                <p class="text-sm text-gray-500">View all records</p>
            </div>
        </a>
        <a href="{{ route('items.index') }}"
           class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-green-300 hover:shadow-md transition-all">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Items</p>
                <p class="text-sm text-gray-500">Manage inventory</p>
            </div>
        </a>
        <a href="{{ url('/addrbook') }}"
           class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:border-purple-300 hover:shadow-md transition-all">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50">
                <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Contacts</p>
                <p class="text-sm text-gray-500">Address book</p>
            </div>
        </a>
    </div>

    {{-- Placeholder area --}}
    <div class="flex-1 min-h-48 rounded-xl border-2 border-dashed border-gray-200 bg-white flex items-center justify-center">
        <div class="text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>
            <p class="mt-3 text-sm text-gray-400">Charts and analytics will appear here.</p>
        </div>
    </div>
</div>
@endsection
