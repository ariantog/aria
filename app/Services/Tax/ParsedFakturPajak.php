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
    ) {}

    /**
     * Invoice total payable: Harga Jual/Penggantian (minus potongan) + PPN + PPnBM.
     * Do not use DPP + PPN — Coretax DPP Nilai Lain is 11/12 of the selling price.
     */
    public function grossIncludingTax(): float
    {
        return round(max(0, $this->grossTotal - $this->discountTotal) + $this->ppn + $this->ppnbm, 2);
    }
}
