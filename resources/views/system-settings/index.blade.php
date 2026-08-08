@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
];
$groupList = $groups->map(fn ($g) => $g ?: 'General')->unique()->values();
$firstGroup = $groupList->first() ?? 'General';
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="{ activeGroup: @js($firstGroup) }">
        <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">System Settings</h2>
            <p class="mt-0.5 text-sm text-gray-500">Configure application wide settings and parameters.</p>
        </div>
        <div class="flex flex-wrap gap-2">
        @if($can['edit'])
        <a href="{{ route('invoice-settings.edit') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Invoice Branding
        </a>
        @endif
        @if($can['create'])
        <a href="{{ route('system-settings.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add New Setting
        </a>
        @endif
        </div>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row">
        {{-- Group nav --}}
        <div class="w-full flex-shrink-0 lg:w-64">
            <nav class="space-y-1">
                @forelse($groupList as $group)
                <button type="button" @click="activeGroup = @js($group)"
                        :class="activeGroup === @js($group) ? 'bg-blue-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100'"
                        class="flex w-full items-center justify-between rounded-xl px-4 py-3 text-sm font-medium transition">
                    <span class="flex items-center gap-3">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $group }}
                    </span>
                </button>
                @empty
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-3 text-sm italic text-gray-500">No groups found</div>
                @endforelse
            </nav>
        </div>

        {{-- Content --}}
        <div class="min-w-0 flex-1">
            @forelse($groupList as $group)
            @php $groupSettings = $settings->filter(fn ($s) => ($s->group ?: 'General') === $group); @endphp
            <div x-show="activeGroup === @js($group)" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 p-5">
                    <h3 class="flex items-center gap-2 text-lg font-bold text-gray-900">
                        <span class="h-6 w-1.5 rounded-full bg-blue-500"></span>
                        {{ $group }} Settings
                    </h3>
                    <span class="rounded bg-gray-200/60 px-2 py-1 font-mono text-xs text-gray-500">{{ $groupSettings->count() }} items</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3 font-bold tracking-wider">Parameter Name</th>
                                <th class="px-6 py-3 font-bold tracking-wider">Identifier (Slug)</th>
                                <th class="px-6 py-3 font-bold tracking-wider">Value</th>
                                <th class="px-6 py-3 text-right font-bold tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($groupSettings as $setting)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-semibold text-gray-900">{{ $setting->name }}</td>
                                <td class="px-6 py-4">
                                    <code class="rounded border border-gray-200 bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-600">{{ $setting->slug }}</code>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    <div class="max-w-xs truncate font-medium">
                                        @php $val = $setting->value; @endphp
                                        {{ is_array($val) || is_object($val) ? (data_get($val, 'name') ?? json_encode($val)) : $val }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-1">
                                        @if($can['edit'])
                                        <a href="{{ route('system-settings.edit', $setting->id) }}"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-blue-50 hover:text-blue-500" title="Edit">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        @endif
                                        @if($can['delete'])
                                        <form method="POST" action="{{ route('system-settings.destroy', $setting->id) }}" onsubmit="return confirm('Are you sure you want to delete this setting?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-500" title="Delete">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center italic text-gray-500">No settings found in this category.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @empty
            <div class="rounded-2xl border border-dashed border-gray-200 p-12 text-center text-sm text-gray-500">No settings configured yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
