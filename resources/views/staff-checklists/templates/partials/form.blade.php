@php
    $template = $template ?? null;
@endphp
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Peran <span class="text-red-500">*</span></label>
        <select name="staff_role_id" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
            <option value="">Pilih peran</option>
            @foreach($staffRoles as $role)
                <option value="{{ $role->id }}" @selected((int) old('staff_role_id', $template?->staff_role_id) === $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        @error('staff_role_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Frekuensi <span class="text-red-500">*</span></label>
        <select name="frequency" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
            @foreach($frequencies as $frequency)
                <option value="{{ $frequency->value }}" @selected(old('frequency', $template?->frequency?->value) === $frequency->value)>{{ $frequency->label() }}</option>
            @endforeach
        </select>
        @error('frequency')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium text-gray-700">Judul <span class="text-red-500">*</span></label>
        <input type="text" name="title" value="{{ old('title', $template?->title) }}" required
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
        @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium text-gray-700">Deskripsi</label>
        <textarea name="description" rows="3"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">{{ old('description', $template?->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Nama route</label>
        <input type="text" name="route_name" value="{{ old('route_name', $template?->route_name) }}" placeholder="mis. items.index"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500">
        @error('route_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Query route</label>
        <input type="text" name="route_query" value="{{ old('route_query', $template?->route_query) }}" placeholder="mis. type=buy"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500">
        @error('route_query')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Urutan</label>
        <input type="number" name="sort_order" min="0" max="999" value="{{ old('sort_order', $template?->sort_order ?? 0) }}"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
        @error('sort_order')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template?->is_active ?? true))
                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            Aktif
        </label>
    </div>
</div>
