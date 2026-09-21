@php
    $fmt = $fmt ?? fn ($v) => format_amount($v);
    $hargaJual = (float) $hargaJual;
    $potongan = (float) $potongan;
    $uangMuka = (float) $uangMuka;
    $dpp = (float) $dpp;
    $ppn = (float) $ppn;
    $ppnbm = (float) $ppnbm;
    $total = (float) $total;
@endphp
<div class="overflow-hidden rounded-lg border border-gray-100" data-testid="faktur-amount-rows">
    <table class="w-full text-sm">
        <tbody>
            <tr class="border-b border-gray-100">
                <th class="px-3 py-1.5 text-left font-normal text-gray-500">Harga jual / penggantian / uang muka / termin</th>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($hargaJual) }}</td>
            </tr>
            <tr class="border-b border-gray-100">
                <th class="px-3 py-1.5 text-left font-normal text-gray-500">Dikurangi potongan harga</th>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($potongan) }}</td>
            </tr>
            <tr class="border-b border-gray-100">
                <th class="px-3 py-1.5 text-left font-normal text-gray-500">Dikurangi uang muka yang telah diterima</th>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($uangMuka) }}</td>
            </tr>
            <tr class="border-b border-gray-100">
                <th class="px-3 py-1.5 text-left font-normal text-gray-400">Dasar pengenaan pajak (diabaikan)</th>
                <td class="px-3 py-1.5 text-right tabular-nums text-gray-400">{{ $fmt($dpp) }}</td>
            </tr>
            <tr class="border-b border-gray-100">
                <th class="px-3 py-1.5 text-left font-normal text-gray-500">Jumlah PPN</th>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($ppn) }}</td>
            </tr>
            <tr class="border-b border-gray-100">
                <th class="px-3 py-1.5 text-left font-normal text-gray-400">Jumlah PPnBM (diabaikan)</th>
                <td class="px-3 py-1.5 text-right tabular-nums text-gray-400">{{ $fmt($ppnbm) }}</td>
            </tr>
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-900">Total (harga jual − potongan − uang muka + PPN)</th>
                <td class="px-3 py-2 text-right font-medium tabular-nums">{{ $fmt($total) }}</td>
            </tr>
        </tbody>
    </table>
</div>
