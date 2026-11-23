<?php

namespace App\Services\Marketplace;

use App\Models\Shopeesync;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Poll Shopee {@see ShopeeAdminOpenApiService::getOrderList} for shops flagged on shopee_syncs
 * (FLEXI / Malaysia export shops where Jubelio does not webhook).
 */
class ShopeeOrderPollService
{
    private const POLL_PUSH_CODE = 3;

  public function __construct(
      private ShopeeAdminOpenApiService $shopeeApi,
      private ShopeeOrderStatusIngest $ingest,
  ) {}

  /**
   * @return array{shops: int, orders_seen: int, ingested: int, skipped: int, errors: list<string>}
   */
  public function poll(?int $onlyShopId = null, ?int $days = null): array
  {
      $summary = [
          'shops' => 0,
          'orders_seen' => 0,
          'ingested' => 0,
          'skipped' => 0,
          'errors' => [],
      ];

      if (! $this->enabled()) {
          return $summary;
      }

      if (! Schema::hasTable('shopee_syncs')) {
          return $summary;
      }

      $days = max(1, min(15, $days ?? (int) config('services.shopee_order_poll.days', 2)));
      $timeTo = time();
      $timeFrom = $timeTo - ($days * 86_400);

      $query = Shopeesync::query()
          ->where('direct_order_ingest', true)
          ->where('shop_id', '>', 0);

      if ($onlyShopId !== null && $onlyShopId > 0) {
          $query->where('shop_id', $onlyShopId);
      }

      $syncRows = $query->orderBy('id')->get();
      $summary['shops'] = $syncRows->count();

      $statuses = $this->pollStatuses();

      foreach ($syncRows as $sync) {
          $shopId = (int) $sync->shop_id;
          if ($shopId <= 0) {
              continue;
          }

          if (! $this->shopeeApi->hasShopAuthorizationFor($shopId)) {
              $summary['errors'][] = "Shop {$shopId}: belum diotorisasi di Shopee Admin OAuth.";
              continue;
          }

          foreach ($statuses as $orderStatus) {
              $cursor = '';
              do {
                  $listBody = $this->shopeeApi->getOrderList($shopId, [
                      'time_range_field' => 'update_time',
                      'time_from' => $timeFrom,
                      'time_to' => $timeTo,
                      'page_size' => 100,
                      'cursor' => $cursor,
                      'order_status' => $orderStatus,
                  ]);

                  if ($listBody === null) {
                      $summary['errors'][] = "Shop {$shopId}: get_order_list gagal ({$orderStatus}).";
                      break;
                  }

                  $response = $listBody['response'] ?? $listBody;
                  if (! is_array($response)) {
                      break;
                  }

                  $orderList = $response['order_list'] ?? [];
                  if (! is_array($orderList)) {
                      $orderList = [];
                  }

                  foreach ($orderList as $row) {
                      if (! is_array($row)) {
                          continue;
                      }

                      $orderSn = (string) ($row['order_sn'] ?? $row['ordersn'] ?? '');
                      if ($orderSn === '') {
                          continue;
                      }

                      $summary['orders_seen']++;

                      $status = (string) ($row['order_status'] ?? $orderStatus);
                      $updateTime = isset($row['update_time']) ? (int) $row['update_time'] : $timeTo;

                      $pushData = [
                          'ordersn' => $orderSn,
                          'order_sn' => $orderSn,
                          'status' => $status,
                          'update_time' => $updateTime,
                      ];

                      $detail = $this->shopeeApi->getOrderDetail($orderSn, $shopId);
                      $detailRow = is_array($detail) ? ($detail['response']['order_list'][0] ?? null) : null;
                      if (is_array($detailRow)) {
                          $status = (string) ($detailRow['order_status'] ?? $status);
                          $pushData['status'] = $status;
                          if (isset($detailRow['item_list']) && is_array($detailRow['item_list'])) {
                              $pushData['item_list'] = $detailRow['item_list'];
                          }
                      }

                      $fullPayload = [
                          'code' => self::POLL_PUSH_CODE,
                          'shop_id' => $shopId,
                          'timestamp' => $updateTime,
                          'data' => $pushData,
                          'source' => 'shopee_poll',
                      ];

                      $ingestResult = $this->ingest->ingest(
                          $shopId,
                          $orderSn,
                          $status,
                          self::POLL_PUSH_CODE,
                          $pushData,
                          $fullPayload,
                          recordWebhookWhenUnified: true,
                      );

                      if ($ingestResult['shadow'] || $ingestResult['jubelio_placeholder'] || $ingestResult['webhook_event']) {
                          $summary['ingested']++;
                      } else {
                          $summary['skipped']++;
                      }
                  }

                  $more = (bool) ($response['more'] ?? false);
                  $cursor = (string) ($response['next_cursor'] ?? '');
                  if (! $more || $cursor === '') {
                      break;
                  }
              } while (true);
          }

          if (Schema::hasColumn('shopee_syncs', 'last_order_poll_at')) {
              $sync->forceFill(['last_order_poll_at' => Carbon::now()])->save();
          }
      }

      if ($summary['errors'] !== []) {
          Log::warning('Shopee order poll completed with errors', $summary);
      }

      return $summary;
  }

  public function enabled(): bool
  {
      return (bool) config('services.shopee_order_poll.enabled', false);
  }

  /**
   * @return list<string>
   */
  private function pollStatuses(): array
  {
      $configured = config('services.shopee_order_poll.order_statuses', []);

      if (is_array($configured) && $configured !== []) {
          return array_values(array_filter(array_map('strval', $configured)));
      }

      return [
          'READY_TO_SHIP',
          'PROCESSED',
          'RETRY_SHIP',
          'SHIPPED',
          'COMPLETED',
      ];
  }
}