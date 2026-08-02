{{-- Permission matrix. Requires an Alpine scope exposing:
     selected (array of permission names) and toggle(name), toggleGroup(names), groupState(names) --}}
<div class="space-y-4">
    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5">
        <span class="text-sm font-medium text-gray-700">
            <span x-text="selected.length"></span> permission(s) selected
        </span>
        <button type="button" @click="selectAll()" class="text-xs font-medium text-blue-600 hover:underline">Toggle All</button>
    </div>

    @foreach($permissions as $module => $perms)
    @php $moduleNames = collect($perms)->pluck('name')->all(); @endphp
    <div class="overflow-hidden rounded-lg border border-gray-200" x-data="{ modOpen: true }">
        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2.5">
            <div class="flex items-center gap-2">
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="checkbox"
                           @change="toggleGroup(@js($moduleNames), $event.target.checked)"
                           :checked="groupAllSelected(@js($moduleNames))"
                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-semibold uppercase tracking-wide text-gray-700">{{ $module }}</span>
                </label>
            </div>
            <button type="button" @click="modOpen = !modOpen" class="text-gray-400 hover:text-gray-600">
                <svg :class="modOpen ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
        <div x-show="modOpen" class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($perms as $perm)
            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-50">
                <input type="checkbox" name="permissions[]" value="{{ $perm['name'] }}"
                       x-model="selected"
                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-gray-700">{{ $perm['name'] }}</span>
            </label>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
