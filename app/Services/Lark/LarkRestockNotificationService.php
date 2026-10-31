<?php

namespace App\Services\Lark;

use App\Models\Item;
use App\Models\LarkRestockRequest;

class LarkRestockNotificationService
{
    public function __construct(
        protected LarkWebhookClient $webhook,
        protected LarkCardBuilder $cards,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendRestockApprovalCard(Item $item, int $suggestedQty, array $context): ?LarkRestockRequest
    {
        if (! config('lark.enabled')) {
            return null;
        }

        $pending = LarkRestockRequest::query()
            ->where('item_id', $item->id)
            ->where('status', LarkRestockRequest::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();

        if ($pending !== null) {
            return $pending;
        }

        $request = LarkRestockRequest::createForItem($item->id, $suggestedQty, $context);
        $card = $this->cards->restockApprovalCard($request);
        $this->webhook->sendInteractiveCard($card, 'stock.restock.pending', 'restock:pending:'.$request->token);

        return $request;
    }
}
