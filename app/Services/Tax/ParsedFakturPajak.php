<?php

namespace App\Services\Tax;

use Illuminate\Support\Carbon;

readonly class ParsedFakturPajak
{
    /**
     * @param  list<array{
     *     line_no: int,
     *     name: string,
     *     unit_price: float,
     *     quantity: float,
     *     total: float,
     * }>  $lineItems
     */
    public function __construct(
        public string $fakturNumber,
        public ?Carbon $fakturDate,
        public ?string $fakturDatePlace,
        public string $sellerName,
        public string $sellerNpwp,
        public string $buyerName,
        public string $buyerNpwp,
        public float $grossTotal,
        public float $discountTotal,
        public float $dpp,
        public float $ppn,
        public float $ppnbm,
        public ?string $signatoryName,
        public string $sourceFormat,
        public array $lineItems = [],
        public float $downPaymentTotal = 0,
    ) {}

    /**
     * Invoice total payable from the six footer rows:
     * harga jual − potongan harga − uang muka + PPN.
     * DPP and PPnBM are stored for tax display only.
     */
    public function grossIncludingTax(): float
    {
        return round(max(0, $this->grossTotal - $this->discountTotal - $this->downPaymentTotal) + $this->ppn, 2);
    }
}
