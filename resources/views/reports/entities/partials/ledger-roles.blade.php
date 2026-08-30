<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="entities-ledger-roles">
    <h3 class="text-sm font-semibold text-gray-900">Ledger roles</h3>
    <p class="mt-1 text-xs text-gray-500">Used by Laba Rugi to keep material / gaji mingguan / tax payments out of opex. Analysis mapping only.</p>

    <form method="POST" action="{{ route('reports.entities.ledger-roles.store') }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
        @csrf
        <div class="flex-1">
            <label class="mb-1 block text-xs text-gray-500" for="ledger-role-account">Account</label>
            <select id="ledger-role-account" name="customer_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="ledger-role-account">
                <option value="">— Select ledger —</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:w-64">
            <label class="mb-1 block text-xs text-gray-500" for="ledger-role-role">Role</label>
            <select id="ledger-role-role" name="role" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="ledger-role-role">
                @foreach($ledgerRoleOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" data-testid="ledger-role-save">Save role</button>
    </form>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                    <th class="px-3 py-2 font-medium">Ledger</th>
                    <th class="px-3 py-2 font-medium">Role</th>
                    <th class="px-3 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ledgerRoles as $role)
                    <tr class="border-b">
                        <td class="px-3 py-2">{{ $role->customer?->name ?? '#'.$role->customer_id }}</td>
                        <td class="px-3 py-2">{{ $role->role?->label() ?? $role->role }}</td>
                        <td class="px-3 py-2 text-right">
                            <form method="POST" action="{{ route('reports.entities.ledger-roles.destroy', $role) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-6 text-center text-gray-500">No ledger roles yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
