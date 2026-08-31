<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Sort By</label>
    <select name="sort" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        @foreach(['codeasc' => 'Code (A-Z)', 'codedesc' => 'Code (Z-A)', 'qtydesc' => 'Quantity (High to Low)', 'qtyasc' => 'Quantity (Low to High)', 'namedesc' => 'Name (Z-A)', 'nameasc' => 'Name (A-Z)', 'priceasc' => 'Price (Low to High)', 'pricedesc' => 'Price (High to Low)', 'idasc' => 'ID (Low to High)', 'iddesc' => 'ID (High to Low)'] as $val => $lbl)
            <option value="{{ $val }}" @selected(($filters['sort'] ?? 'codeasc') === $val)>{{ $lbl }}</option>
        @endforeach
    </select>
</div>
<div class="flex items-center gap-2 py-2">
    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-600">
        <input type="checkbox" name="show0" value="show" @checked(($filters['show0'] ?? '') === 'show') class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        Show empty stock (&lt; 1)
    </label>
</div>
