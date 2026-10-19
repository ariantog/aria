@if($canEditLarkNotify ?? false)
<div class="mt-6 border-t border-gray-200 pt-6">
    <h3 class="text-base font-semibold text-gray-900">Lark @ mentions</h3>
    <p class="mt-1 text-sm text-gray-500">Open ID from Lark (Feishu) user profile. Used when ops cards @ roster by staff role. Disable notify to exclude this user from mentions while keeping the ID on file.</p>
    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Lark Open ID</label>
            <input type="text" name="lark_open_id" value="{{ old('lark_open_id', $editUser->lark_open_id) }}" maxlength="64"
                   placeholder="ou_xxxxxxxx"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500">
            @error('lark_open_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end pb-2">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="lark_notify_enabled" value="0">
                <input type="checkbox" name="lark_notify_enabled" value="1"
                       @checked(old('lark_notify_enabled', $editUser->lark_notify_enabled ?? true))
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                Aktifkan @ mention Lark
            </label>
        </div>
    </div>
</div>
@endif
