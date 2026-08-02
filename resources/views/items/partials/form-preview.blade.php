<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-green-500/10 p-2">
            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Item Summary Preview</h3>
            <p class="text-sm text-gray-500">Summary of items to be generated</p>
        </div>
    </div>
    <div class="p-5">
        <template x-if="previewItems.length > 0">
            <div class="space-y-1">
                <div class="grid grid-cols-5 gap-4 px-3 py-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <div class="col-span-2">SKU / Code</div>
                    <div class="col-span-3">Generated Name</div>
                </div>
                <div class="max-h-[400px] space-y-1 overflow-y-auto pr-2">
                    <template x-for="(row, idx) in previewItems" :key="idx">
                        <div class="grid grid-cols-5 items-center gap-4 rounded-lg border border-gray-100 bg-gray-50/50 p-3">
                            <div class="col-span-2 break-all font-mono text-sm font-semibold text-blue-600" x-text="row.sku"></div>
                            <div class="col-span-3 text-sm font-medium text-gray-900" x-text="row.name"></div>
                        </div>
                    </template>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="text-xs text-gray-500">Total Items to Create: <span class="font-bold text-gray-900" x-text="previewItems.length"></span></p>
                </div>
            </div>
        </template>
        <template x-if="previewItems.length === 0">
            <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500">
                <svg class="mb-2 h-10 w-10 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <p class="text-sm">Enter PCode and select Warna/Size to see preview.</p>
            </div>
        </template>
    </div>
</div>
