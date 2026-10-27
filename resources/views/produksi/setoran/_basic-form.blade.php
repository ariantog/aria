{{-- Basic Information Form (shared by produksi.edit and produksi.setoran.edit) --}}
@php
    $canEditKode = $canEditKode ?? false;
    $canEditQuantity = $canEditQuantity ?? false;
@endphp
<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Basic Information</h3>
    <p class="mt-1 text-sm text-gray-500">
        @if($canEditKode || $canEditQuantity)
            Update kode, quantity, color, customer, and surat jalan details.
        @else
            Update color, customer, and surat jalan details.
        @endif
    </p>
    <form method="POST" action="{{ route('produksi.update', $produksi->id) }}" class="mt-4 space-y-4">
        @csrf
        @method('PATCH')
        @unless($canEditKode)
        <input type="hidden" name="temp_name" value="{{ old('temp_name', $produksi->temp_name) }}">
        @endunless
        @if(! $canEditQuantity && ! $produksi->isSplitChild())
        <input type="hidden" name="quantity" value="{{ old('quantity', $produksi->quantity) }}">
        @endif
        @if($canEditKode || $canEditQuantity)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @if($canEditKode)
            <div class="space-y-2">
                <label class="text-sm font-medium">Kode</label>
                <input name="temp_name" value="{{ old('temp_name', $produksi->temp_name) }}" required placeholder="e.g. APJ CJ00414" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('temp_name')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            @endif
            @if($canEditQuantity)
            <div class="space-y-2">
                <label class="text-sm font-medium">Quantity</label>
                <input type="number" name="quantity" min="1" value="{{ old('quantity', $produksi->quantity) }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('quantity')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            @elseif($canEditKode && $produksi->isSplitChild())
            <div class="space-y-2">
                <label class="text-sm font-medium">Quantity</label>
                <p class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">{{ $produksi->quantity }}</p>
                <p class="text-xs text-gray-500">Split entries cannot change quantity here.</p>
            </div>
            @endif
        </div>
        @endif
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
