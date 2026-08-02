{{-- Reassign Jahit + QC forms (shared) --}}
{{-- Reassign Jahit --}}
<div class="rounded-xl border border-l-4 border-zinc-200 border-l-emerald-500 bg-white p-6 shadow-sm">
    <h3 class="flex items-center gap-2 text-lg font-semibold">
        <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"/></svg>
        Reassign Jahit
    </h3>
    <p class="mt-1 text-sm text-zinc-500">Change the worker assigned to the sewing stage.</p>
    <form method="POST" action="{{ route('produksi.ganti-jahit', $produksi->id) }}" class="mt-4 flex flex-col items-end gap-4 sm:flex-row">
        @csrf
        @method('PATCH')
        <div class="flex-1 space-y-2">
            <label class="text-sm font-medium">Select Jahit Worker</label>
            <div class="relative" x-data="asyncCombobox({ endpoint: '{{ route('produksi.workers.lookup') }}', additionalParams: { type: 'jahit' }, placeholder: 'Search worker...', hiddenField: 'ganti_jahit_id', initial: @js($produksi->jahit ? ['id' => $produksi->jahit->id, 'name' => $produksi->jahit->name] : null) })" x-init="if(selected){ query = selected.name }">
                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)" :placeholder="placeholder" autocomplete="off" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <input type="hidden" id="ganti_jahit_id" name="jahit_id" value="{{ $produksi->jahit->id ?? '' }}">
                <div x-show="open" x-cloak class="combobox-options" x-ref="optionsList">
                    <template x-for="(item, i) in items" :key="item.id">
                        <div class="combobox-option" :class="{ 'active': i === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = i">
                            <span x-text="item.name"></span>
                        </div>
                    </template>
                    <div x-show="!loading && items.length === 0" class="combobox-option text-gray-400">No results</div>
                </div>
            </div>
        </div>
        <button type="submit" @unless($canEdit) disabled @endunless class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">Update Jahit</button>
    </form>
</div>

{{-- Reassign QC --}}
<div class="rounded-xl border border-l-4 border-zinc-200 border-l-blue-500 bg-white p-6 shadow-sm">
    <h3 class="flex items-center gap-2 text-lg font-semibold">
        <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Reassign QC
    </h3>
    <p class="mt-1 text-sm text-zinc-500">Change the worker assigned to the QC stage.</p>
    <form method="POST" action="{{ route('produksi.assign-qc', $produksi->id) }}" class="mt-4 flex flex-col items-end gap-4 sm:flex-row">
        @csrf
        @method('PATCH')
        <div class="flex-1 space-y-2">
            <label class="text-sm font-medium">Select QC Worker</label>
            <div class="relative" x-data="asyncCombobox({ endpoint: '{{ route('produksi.workers.lookup') }}', additionalParams: { type: 'qc' }, placeholder: 'Search worker...', hiddenField: 'assign_qc_id', initial: @js($produksi->qc ? ['id' => $produksi->qc->id, 'name' => $produksi->qc->name] : null) })" x-init="if(selected){ query = selected.name }">
                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)" :placeholder="placeholder" autocomplete="off" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <input type="hidden" id="assign_qc_id" name="qc_id" value="{{ $produksi->qc->id ?? '' }}">
                <div x-show="open" x-cloak class="combobox-options" x-ref="optionsList">
                    <template x-for="(item, i) in items" :key="item.id">
                        <div class="combobox-option" :class="{ 'active': i === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = i">
                            <span x-text="item.name"></span>
                        </div>
                    </template>
                    <div x-show="!loading && items.length === 0" class="combobox-option text-gray-400">No results</div>
                </div>
            </div>
        </div>
        <button type="submit" @unless($canEdit) disabled @endunless class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">Update QC</button>
    </form>
</div>
