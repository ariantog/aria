<?php

namespace App\Services\Lark;

use App\Models\Item;
use App\Models\Jubelioorder;

class LarkAlertLinkBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function jubelioOversellActions(
        string $invoice,
        ?string $salesorderId,
        ?int $firstItemId = null,
        ?int $jubelioOrderRowId = null,
    ): array {
        $cards = app(LarkCardBuilder::class);
        $actions = [];

        $ariaUrl = $this->ariaJubelioOrdersUrl($invoice, $jubelioOrderRowId);
        if ($ariaUrl !== null) {
            $actions[] = $cards->urlButton('Buka di Aria', $ariaUrl);
        }

        if ($firstItemId !== null && $firstItemId > 0) {
            $item = Item::query()->find($firstItemId);
            if ($item !== null) {
                $actions[] = $cards->urlButton('SKU di Aria', $item->showUrl());
            }
        }

        $jubelioUrl = $this->jubelioWebOrderUrl($salesorderId, $invoice);
        if ($jubelioUrl !== null) {
            $actions[] = $cards->urlButton('Buka di Jubelio', $jubelioUrl);
        }

        return $actions;
    }

    public function ariaJubelioOrdersUrl(string $invoice, ?int $jubelioOrderRowId = null): ?string
    {
        if ($jubelioOrderRowId !== null && $jubelioOrderRowId > 0) {
            return route('jubelio.show', $jubelioOrderRowId);
        }

        if ($invoice === '') {
            return null;
        }

        $order = Jubelioorder::query()->where('invoice', $invoice)->orderByDesc('id')->first();
        if ($order !== null) {
            return route('jubelio.show', $order);
        }

        return route('jubelio.index', ['invoice' => $invoice, 'status' => 'all']);
    }

    public function jubelioWebOrderUrl(?string $salesorderId, string $invoice): ?string
    {
        $template = config('lark.jubelio_order_url_template');
        if (! is_string($template) || trim($template) === '') {
            return null;
        }

        $url = str_replace(
            ['{salesorder_id}', '{invoice}'],
            [rawurlencode((string) ($salesorderId ?? '')), rawurlencode($invoice)],
            $template,
        );

        if (str_contains($url, '{')) {
            return null;
        }

        return $url;
    }
}
