<?php

namespace App\Services\Lark;

use App\Models\LarkRestockRequest;

class LarkCardBuilder
{
    /**
     * @param  array<string, mixed>  $fields  label => value lines
     */
    public function simpleAlert(
        string $title,
        string $template,
        array $fields,
        ?string $mentionMd = null,
        ?array $actions = null,
    ): array {
        $lines = [];
        if ($mentionMd) {
            $lines[] = $mentionMd;
        }
        foreach ($fields as $label => $value) {
            $lines[] = '**'.$label.':** '.$value;
        }

        $elements = [
            [
                'tag' => 'div',
                'text' => [
                    'tag' => 'lark_md',
                    'content' => implode("\n", $lines),
                ],
            ],
        ];

        if ($actions !== null && $actions !== []) {
            $elements[] = [
                'tag' => 'action',
                'actions' => $actions,
            ];
        }

        return [
            'config' => ['wide_screen_mode' => true],
            'header' => [
                'title' => ['tag' => 'plain_text', 'content' => $title],
                'template' => $template,
            ],
            'elements' => $elements,
        ];
    }

    /**
     * Interactive restock approval card (input + submit callback).
     */
    public function restockApprovalCard(LarkRestockRequest $request): array
    {
        $ctx = $request->context_snapshot ?? [];
        $code = (string) ($ctx['code'] ?? 'SKU');
        $name = (string) ($ctx['name'] ?? '');
        $suggested = (int) $request->suggested_qty;

        $md = implode("\n", array_filter([
            '**'.$code.'**'.($name !== '' ? ' — '.$name : ''),
            '**Saran restock:** '.$suggested.' unit',
            isset($ctx['aria_qty']) ? '**Stok Aria (fisik):** '.$ctx['aria_qty'] : null,
            isset($ctx['jubelio_available']) ? '**Jubelio available:** '.$ctx['jubelio_available'] : null,
            isset($ctx['cover_days_aria']) ? '**Cover Aria:** ~'.$ctx['cover_days_aria'].' hari' : null,
            isset($ctx['cover_days_jubelio']) ? '**Cover Jubelio:** ~'.$ctx['cover_days_jubelio'].' hari' : null,
            isset($ctx['net_sell_30d']) ? '**Net jual 30h:** '.$ctx['net_sell_30d'] : null,
        ]));

        $itemUrl = isset($ctx['item_url']) ? (string) $ctx['item_url'] : null;

        $elements = [
            [
                'tag' => 'div',
                'text' => ['tag' => 'lark_md', 'content' => $md],
            ],
            [
                'tag' => 'input_number',
                'name' => 'qty_restock',
                'placeholder' => ['tag' => 'plain_text', 'content' => 'Qty restock'],
                'default_value' => (string) max(0, $suggested),
                'min' => '0',
            ],
            [
                'tag' => 'action',
                'actions' => array_values(array_filter([
                    [
                        'tag' => 'button',
                        'text' => ['tag' => 'plain_text', 'content' => 'Terapkan restock'],
                        'type' => 'primary',
                        'behaviors' => [[
                            'type' => 'callback',
                            'value' => [
                                'action' => 'restock_apply',
                                'token' => $request->token,
                            ],
                        ]],
                    ],
                    $itemUrl ? [
                        'tag' => 'button',
                        'text' => ['tag' => 'plain_text', 'content' => 'Buka di Aria'],
                        'type' => 'default',
                        'url' => $itemUrl,
                    ] : null,
                ])),
            ],
        ];

        return [
            'config' => ['wide_screen_mode' => true],
            'header' => [
                'title' => ['tag' => 'plain_text', 'content' => 'Stok rendah — restock'],
                'template' => 'orange',
            ],
            'elements' => $elements,
        ];
    }

    public function restockResultCard(string $title, string $body, string $template = 'green'): array
    {
        return $this->simpleAlert($title, $template, ['Hasil' => $body]);
    }
}
