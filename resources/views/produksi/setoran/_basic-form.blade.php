{{-- Basic Information Form (shared by produksi.edit and produksi.setoran.edit) --}}
<div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Basic Information</h3>
    <p class="mt-1 text-sm text-zinc-500">Update color, customer, and surat jalan details.</p>
    <form method="POST" action="{{ route('produksi.update', $produksi->id) }}" class="mt-4 space-y-4">
        @csrf
        @method('PATCH')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <label class="text-sm font-medium">Warna</label>
                <input name="warna" value="{{ old('warna', $produksi->warna) }}" placeholder="e.g. NAVY" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium">Customer</label>
                <input name="customer" value="{{ old('customer', $produksi->customer) }}" placeholder="e.g. CORE NATION" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium">Surat Jalan Potong (SJP)</label>
            <input name="surat_jalan_potong" value="{{ old('surat_jalan_potong', $produksi->surat_jalan_potong) }}" placeholder="SJP Number" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="pt-2">
            <button type="submit" @unless($canEdit) disabled @endunless class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">Save Changes</button>
        </div>
    </form>
</div>
