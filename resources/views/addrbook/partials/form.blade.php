@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $val = function ($field, $default = '') use ($isEdit, $addrbook) {
        if (old($field) !== null) return old($field);
        if ($isEdit && isset($addrbook)) return $addrbook->{$field};
        return $default;
    };
    $preselected = $preselected_type_id ?? null;
    $typeValue = old('type', $isEdit ? (string) ($addrbook->type instanceof \App\Enums\AddrbookType ? $addrbook->type->value : $addrbook->type) : ($preselected ? (string) $preselected : ''));
    $isOnline = (bool) old('is_online', $isEdit ? $addrbook->is_online : false);
    $ppn = (bool) old('ppn', $isEdit ? $addrbook->ppn : false);
@endphp

<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    {{-- Basic Information --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm md:col-span-2">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Basic Information</h3>
            <p class="text-xs text-gray-500">Primary details for this contact.</p>
        </div>
        <div class="space-y-4 p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ $val('name') }}" required
                           placeholder="e.g. PT. Maju Jaya"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('name') border-red-500 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="type" class="mb-1 block text-sm font-medium text-gray-700">Type <span class="text-red-500">*</span></label>
                    <select id="type" name="type" required
                            {{ (!$isEdit && $preselected) ? 'disabled' : '' }}
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('type') border-red-500 @enderror disabled:bg-gray-100">
                        <option value="">Select Type</option>
                        @foreach($types as $t)
                            <option value="{{ $t['id'] }}" @selected((string) $t['id'] === (string) $typeValue)>{{ $t['name'] }}</option>
                        @endforeach
                    </select>
                    @if(!$isEdit && $preselected)
                        {{-- disabled selects don't submit; send value via hidden input --}}
                        <input type="hidden" name="type" value="{{ $preselected }}">
                    @endif
                    @error('type')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="contact_person" class="mb-1 block text-sm font-medium text-gray-700">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" value="{{ $val('contact_person') }}"
                           placeholder="e.g. Budi Santoso"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('contact_person') border-red-500 @enderror">
                    @error('contact_person')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Phone / WhatsApp</label>
                    <input type="text" id="phone" name="phone" value="{{ $val('phone') }}"
                           placeholder="e.g. 08123456789"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('phone') border-red-500 @enderror">
                    @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ $val('email') }}"
                           placeholder="e.g. budi@example.com"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                    @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="address" class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                <textarea id="address" name="address" rows="4" placeholder="Full address here..."
                          class="min-h-[100px] w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('address') border-red-500 @enderror">{{ $val('address') }}</textarea>
                @error('address')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- Settings --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Settings</h3>
            <p class="text-xs text-gray-500">Status and tax configuration.</p>
        </div>
        <div class="space-y-4 p-5">
            <div x-data="{ on: {{ $isOnline ? 'true' : 'false' }} }" class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">Online</p>
                    <p class="text-xs text-gray-500">Is this contact from an online source?</p>
                </div>
                <input type="hidden" name="is_online" :value="on ? 1 : 0">
                <button type="button" @click="on = !on" :class="on ? 'bg-blue-600' : 'bg-gray-300'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full transition-colors">
                    <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 transform rounded-full bg-white transition-transform"></span>
                </button>
            </div>

            <div x-data="{ on: {{ $ppn ? 'true' : 'false' }} }" class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">PPN ({{ $ppn_rate }}%)</p>
                    <p class="text-xs text-gray-500">Apply {{ $ppn_rate }}% tax for this contact?</p>
                </div>
                <input type="hidden" name="ppn" :value="on ? 1 : 0">
                <button type="button" @click="on = !on" :class="on ? 'bg-blue-600' : 'bg-gray-300'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full transition-colors">
                    <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 transform rounded-full bg-white transition-transform"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Financials --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-sm font-semibold text-gray-900">{{ $isEdit ? 'Current Financials' : 'Financials' }}</h3>
            <p class="text-xs text-gray-500">{{ $isEdit ? 'View current balance.' : 'Initial setup for accounting.' }}</p>
        </div>
        <div class="space-y-4 p-5">
            @if($isEdit)
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-md bg-blue-100 p-2">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Current Balance</p>
                            <p class="text-xl font-bold text-gray-900">IDR {{ number_format((float) ($addrbook->stat->balance ?? 0), 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
                <p class="text-center text-xs text-gray-500">Balance adjustments should be made via Transactions.</p>
            @else
                <div>
                    <label for="initial_balance" class="mb-1 block text-sm font-medium text-gray-700">Initial Balance</label>
                    <input type="number" id="initial_balance" name="initial_balance" value="{{ old('initial_balance', 0) }}" min="0" placeholder="0"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('initial_balance') border-red-500 @enderror">
                    @error('initial_balance')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <p class="text-xs text-gray-500">Set the starting balance. Positive = Receivable, Negative = Payable (if applicable).</p>
            @endif
        </div>
    </div>
</div>
