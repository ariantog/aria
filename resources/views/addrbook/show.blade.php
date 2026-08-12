@extends('layouts.app')

@section('title', $addrbook->name . ' - Detail')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => 'Detail', 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
];
$balance = (float) ($addrbook->stat->balance ?? 0);
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    {{-- Header --}}
    <div class="mb-4 flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
        <div class="flex items-start gap-5">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-lg">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <div class="mb-1 flex items-center gap-3">
                    <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">{{ $addrbook->name }}</h1>
                    <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">{{ $addrbook->type_name }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
                    <span class="font-mono">ID: #{{ $addrbook->id }}</span>
                    @if($addrbook->memberId)<span class="font-mono">Member ID: {{ $addrbook->memberId }}</span>@endif
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $addrbook->is_online ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        {{ $addrbook->is_online ? 'Online' : 'Offline' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.history.back()" class="inline-flex h-10 items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back
            </button>
            <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}/edit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-medium text-white shadow-lg hover:bg-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit Details
            </a>
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'detail'])

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- General Info --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 shadow-sm lg:col-span-2">
            <div class="border-b border-gray-200 bg-gray-50/50 p-6">
                <h3 class="text-lg font-bold text-gray-900">General Information</h3>
                <p class="text-sm text-gray-500">Primary contact and basic details</p>
            </div>
            <div class="space-y-8 bg-white p-6 md:p-8">
                <div class="grid grid-cols-1 gap-x-12 gap-y-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Contact Person</p>
                        <div class="flex items-center gap-3 font-medium text-gray-900">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
                            {{ $addrbook->contact_person ?: 'No data available' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Phone Number</p>
                        <div class="flex items-center gap-3 font-medium text-gray-900">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></div>
                            {{ $addrbook->phone ?: 'No data available' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Email Address</p>
                        <div class="flex items-center gap-3 font-medium text-gray-900">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                            {{ $addrbook->email ?: 'No data available' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Tax Status (PPN)</p>
                        <div class="flex flex-wrap gap-2">
                            @if($addrbook->ppn)
                                <span class="rounded-md bg-emerald-100 px-3 py-1 text-sm font-medium text-emerald-700">PPN Active ({{ $ppn_rate }}%)</span>
                            @else
                                <span class="rounded-md border border-gray-200 px-3 py-1 text-sm text-gray-400">PPN Non-Active</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="space-y-3 border-t border-gray-100 pt-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Full Address</p>
                    <div class="flex items-start gap-4 rounded-2xl border border-gray-200 bg-gray-50/50 p-6">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-gray-500 shadow-sm"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600 md:text-base">{{ $addrbook->address ?: 'No address provided' }}</p>
                    </div>
                </div>

                @if($addrbook->description)
                <div class="space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Description / Internal Notes</p>
                    <p class="text-sm italic text-gray-600">{{ $addrbook->description }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Sidebar cards --}}
        <div class="space-y-6">
            <div class="relative overflow-hidden rounded-2xl bg-blue-600 text-white shadow-xl">
                <div class="absolute right-0 top-0 p-8 opacity-10">
                    <svg class="h-32 w-32 rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div class="p-8">
                    <p class="mb-1 text-sm font-medium uppercase tracking-widest text-blue-100">Current Balance</p>
                    <h3 class="truncate text-3xl font-extrabold">IDR {{ number_format($balance, 0, ',', '.') }}</h3>
                    <p class="mt-4 text-xs text-blue-200">Total outstanding or credit balance</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 shadow-sm">
                <div class="p-6 pb-2">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400">Account Metadata</h3>
                </div>
                <div class="space-y-4 p-6">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Joined on</span>
                        <span class="font-medium">{{ $addrbook->created_at ? $addrbook->created_at->translatedFormat('d F Y') : 'N/A' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Last activity</span>
                        <span class="font-medium">{{ optional($addrbook->dailies->first())->date ? \Carbon\Carbon::parse($addrbook->dailies->first()->date)->format('d/m/Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
