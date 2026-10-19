<?php

namespace App\Services\Lark;

use App\Models\Jubelioorder;

class JubelioOversellBlockedNotifier
{
    public function __construct(
        protected OperationalAlertDispatcher $alerts,
        protected LarkAlertLinkBuilder $links,
    ) {}

    /**
     * @param  array{message: string, items: list<array{item_id: int, code: string, available: float, needed: float}>}  $stockError
     */
    public function notify(Jubelioorder $order, array $stockError): void
    {
        if (! config('lark.enabled')) {
            return;
        }

        $summary = collect($stockError['items'] ?? [])
            ->map(fn (array $r) => $r['code'].' (avail '.$r['available'].', need '.$r['needed'].')')
            ->implode('; ');

        $fingerprint = 'oversell:blocked:'.$order->id.':'.hash('sha256', $summary);
        $firstItemId = (int) ($stockError['items'][0]['item_id'] ?? 0);

        $this->alerts->dispatch(
            'jubelio.oversell.blocked',
            $fingerprint,
            'critical',
            'Posting Jubelio diblok — stok tidak cukup',
            [
                'Invoice' => (string) $order->invoice,
                'Order ID' => (string) $order->id,
                'Detail' => $summary,
                'Pesan' => $stockError['message'] ?? '',
                '_actions' => $this->links->jubelioOversellActions(
                    (string) $order->invoice,
                    (string) ($order->jubelio_order_id ?? ''),
                    $firstItemId > 0 ? $firstItemId : null,
                    (int) $order->id,
                ),
            ],
        );
    }
}
