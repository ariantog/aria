<table class="invoice-signatures">
    <tr>
        <td>Mengetahui</td>
        <td>Pemberi</td>
        <td>Penerima</td>
    </tr>
    <tr>
        <td><div class="sign-space"></div></td>
        <td><div class="sign-space"></div></td>
        <td><div class="sign-space"></div></td>
    </tr>
    <tr>
        <td class="sign-line">(...........................)</td>
        <td class="sign-line">({{ $transaction->sender?->name ?? '...........................' }})</td>
        <td class="sign-line">({{ $transaction->receiver?->name ?? '...........................' }})</td>
    </tr>
</table>
