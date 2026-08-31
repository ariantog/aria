@php
$checklist = $checklist ?? [];
$groups = $checklist['groups'] ?? [];
$roles = $checklist['roles'] ?? collect();
$summary = $checklist['summary'] ?? ['total' => 0, 'completed' => 0, 'pending' => 0];
$pending = $summary['pending'] ?? max(0, ($summary['total'] ?? 0) - ($summary['completed'] ?? 0));
$showFullPageLink = $showFullPageLink ?? false;
@endphp

@if($checklist['has_checklists'] ?? false)
<div class="flex flex-col gap-4" data-testid="staff-checklist-panel"
     x-data="{
        async toggle(templateId) {
            const res = await fetch(`/checklist/${templateId}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) return;
            const data = await res.json();
            window.dispatchEvent(new CustomEvent('checklist-toggled', { detail: data }));
        }
     }"
     @checklist-toggled.window="location.reload()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Checklist peran</h3>
            <p class="text-sm text-gray-500">Tugas operasional berdasarkan peran Anda — harian, mingguan, dwi minggu, dan bulanan.</p>
        </div>
        <div class="flex items-center gap-3 text-sm text-gray-600">
            <span>
                <span class="font-semibold text-gray-900">{{ $summary['completed'] }}/{{ $summary['total'] }}</span> selesai periode ini
            </span>
            @if($pending > 0)
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $pending }} belum</span>
            @endif
            @if($showFullPageLink)
            <a href="{{ route('my-checklist.index') }}" class="text-sm font-medium text-blue-700 hover:underline">Halaman checklist →</a>
            @endif
        </div>
    </div>

    @if($roles->isNotEmpty())
    <div class="flex flex-wrap gap-2">
        @foreach($roles as $role)
        <span class="rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-800">{{ $role->name }}</span>
        @endforeach
    </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @foreach($groups as $group)
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="staff-checklist-{{ $group['frequency'] }}">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">{{ $group['label'] }}</h4>
                    <p class="text-xs text-gray-500">Periode: {{ $group['period_key'] }}</p>
                </div>
                <span class="text-xs font-medium {{ $group['completed'] === $group['total'] ? 'text-green-700' : 'text-amber-700' }}">
                    {{ $group['completed'] }}/{{ $group['total'] }}
                </span>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($group['items'] as $item)
                <li class="flex items-start gap-3 px-5 py-3 text-sm">
                    <button type="button"
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border {{ $item['completed'] ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 bg-white hover:border-blue-400' }}"
                            title="{{ $item['completed'] ? 'Batalkan centang' : 'Tandai selesai' }}"
                            @click="toggle({{ $item['id'] }})"
                            data-testid="checklist-item-{{ $item['id'] }}">
                        @if($item['completed'])
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        @endif
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium {{ $item['completed'] ? 'text-gray-400 line-through' : 'text-gray-900' }}">{{ $item['title'] }}</p>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-gray-500">{{ $item['role_name'] }}</span>
                        </div>
                        @if($item['description'])
                        <p class="mt-0.5 text-xs text-gray-500">{{ $item['description'] }}</p>
                        @endif
                        @if($item['url'])
                        <a href="{{ $item['url'] }}" class="mt-1 inline-block text-xs font-medium text-blue-700 hover:underline">Buka halaman →</a>
                        @endif
                        @if($item['completed'] && $item['completed_at'])
                        <p class="mt-0.5 text-xs text-green-700">Selesai {{ $item['completed_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
        @endforeach
    </div>
</div>
@endif
