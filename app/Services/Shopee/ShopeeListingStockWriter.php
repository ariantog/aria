<?php

namespace App\Services\Shopee;

use App\Models\Item;
use Illuminate\Support\Facades\Log;

class ShopeeListingStockWriter
{
    public function __construct(
        private ShopeeStockOpenApiService $openApi,
    ) {}

    /**
     * @return array{ok: bool, http_status: int, snippet: ?string, error: ?string}
     */
    public function pushLocationStock(Item $item, string $locationId, int $qty): array
    {
        $itemId = (int) ($item->shopee_item_id ?? 0);
        $modelId = (int) ($item->shopee_model_id ?? 0);
        $locationId = trim($locationId);

        if ($itemId <= 0 || $modelId <= 0) {
            return [
                'ok' => false,
                'http_status' => 0,
                'snippet' => null,
                'error' => 'SKU belum link Shopee item/model.',
            ];
        }

        $body = [
            'item_id' => $itemId,
            'stock_list' => [
                [
                    'model_id' => $modelId,
                    'seller_stock' => [
                        [
                            'location_id' => $locationId,
                            'stock' => max(0, $qty),
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->openApi->shopApiPost('/api/v2/product/update_stock', $body);
        $httpStatus = $response->status();
        $snippet = mb_substr($response->body(), 0, 500);

        $decoded = $this->openApi->decodeShopResponse($response, 'update_stock');
        if ($decoded === null) {
            Log::warning('Shopee listing stock update failed', [
                'item_id' => $item->id,
                'shopee_item_id' => $itemId,
                'location_id' => $locationId,
                'qty' => $qty,
                'http' => $httpStatus,
            ]);

            return [
                'ok' => false,
                'http_status' => $httpStatus,
                'snippet' => $snippet,
                'error' => 'Shopee update_stock rejected.',
            ];
        }

        return [
            'ok' => true,
            'http_status' => $httpStatus,
            'snippet' => $snippet,
            'error' => null,
        ];
    }
}