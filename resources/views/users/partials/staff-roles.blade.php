@if($canAssignStaffRoles ?? false)
@php
    $assignedStaffRoleIds = collect($assignedStaffRoleIds ?? old('staff_role_ids', []))->map(fn ($id) => (int) $id)->all();
@endphp
<div class="md:col-span-2">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
        <div class="mb-3">
            <label class="block text-sm font-medium text-gray-900">Peran operasional (checklist)</label>
            <p class="mt-0.5 text-sm text-gray-500">Pilih satu atau lebih peran untuk menampilkan checklist harian/mingguan/bulanan di dashboard pengguna ini.</p>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" data-testid="staff-role-assignment">
            @foreach($staffRoles as $staffRole)
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 bg-white p-3 hover:border-blue-300">
                    <input type="checkbox"
                           name="staff_role_ids[]"
                           value="{{ $staffRole->id }}"
                           @checked(in_array($staffRole->id, $assignedStaffRoleIds, true))
                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-900">{{ $staffRole->name }}</span>
                        @if($staffRole->description)
                            <span class="mt-0.5 block text-xs text-gray-500">{{ $staffRole->description }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        @error('staff_role_ids')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('staff_role_ids.*')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
@endif
