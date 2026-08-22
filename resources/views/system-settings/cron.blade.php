@extends('layouts.app')

@section('title', 'Cron Manager')

@section('content')
@php
$breadcrumbs = array_values(array_filter([
    ($can['general_settings'] ?? false) ? ['title' => 'System Settings', 'href' => route('system-settings.index')] : null,
    ['title' => 'Cron Manager', 'href' => route('scheduled-tasks.index')],
]));
$frequencyOptions = [
    'everyMinute' => 'Every Minute',
    'everyTwoMinutes' => 'Every Two Minutes',
    'everyFiveMinutes' => 'Every Five Minutes',
    'everyTenMinutes' => 'Every Ten Minutes',
    'everyThirtyMinutes' => 'Every Thirty Minutes',
    'hourly' => 'Hourly',
    'everyTwoHours' => 'Every Two Hours',
    'everyThreeHours' => 'Every Three Hours',
    'everySixHours' => 'Every Six Hours',
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'quarterly' => 'Quarterly',
    'yearly' => 'Yearly',
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="cronManager()">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Cron Manager</h2>
        <p class="mt-0.5 text-sm text-gray-500">Manage scheduled tasks and their execution cycles.</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 p-5">
            <h3 class="text-base font-bold text-gray-900">Scheduled Tasks</h3>
            <p class="text-sm text-gray-500">List of all tasks registered in the system.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold tracking-wider">Name</th>
                        <th class="px-6 py-3 font-bold tracking-wider">Frequency</th>
                        <th class="px-6 py-3 text-center font-bold tracking-wider">Status</th>
                        <th class="px-6 py-3 font-bold tracking-wider">Last Run</th>
                        <th class="px-6 py-3 text-right font-bold tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($tasks as $task)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-gray-900">{{ $task->name }}</div>
                            <div class="text-xs text-gray-400">{{ $task->command }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">{{ $frequencyOptions[$task->frequency] ?? $task->frequency }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($can['edit'])
                            <form method="POST" action="{{ route('scheduled-tasks.toggle', $task->id) }}" class="inline">
                                @csrf
                                <button type="submit"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $task->active ? 'bg-blue-600' : 'bg-gray-300' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $task->active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </form>
                            @else
                            <span class="inline-flex h-6 w-11 items-center rounded-full {{ $task->active ? 'bg-blue-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white {{ $task->active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-700">{{ $task->last_run_at ? \Illuminate\Support\Carbon::parse($task->last_run_at)->format('d/m/Y H:i') : 'Never' }}</td>
                        <td class="px-6 py-4 text-right">
                            @if($can['edit'])
                            @php $taskPayload = json_encode(['id' => $task->id, 'name' => $task->name, 'frequency' => $task->frequency, 'active' => (bool) $task->active, 'description' => $task->description], JSON_HEX_APOS | JSON_HEX_QUOT); @endphp
                            <button type="button" @click='openEdit({!! $taskPayload !!})'
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center italic text-gray-500">No scheduled tasks found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Edit modal --}}
    <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="editing = null">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @click.away="editing = null">
            <h3 class="text-lg font-bold text-gray-900">Edit Task: <span x-text="form.name"></span></h3>
            <p class="mt-0.5 text-sm text-gray-500">Update the name and execution schedule for this task.</p>
            <form method="POST" :action="updateUrl" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="active" :value="form.active ? 1 : 0">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" x-model="form.name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Schedule Frequency</label>
                    <select name="frequency" x-model="form.frequency" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        @foreach($frequencyOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" name="description" x-model="form.description" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="editing = null" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function cronManager() {
    return {
        editing: null,
        form: { id: null, name: '', frequency: '', active: true, description: '' },
        get updateUrl() {
            return this.form.id ? `/cron-manager/${this.form.id}` : '#';
        },
        openEdit(task) {
            this.form = {
                id: task.id,
                name: task.name || '',
                frequency: task.frequency || '',
                active: !!task.active,
                description: task.description || '',
            };
            this.editing = task.id;
        },
    };
}
</script>
@endpush
@endsection
