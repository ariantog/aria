<?php

namespace App\Services\Marketplace;

use App\Models\ShopeeFlexiShop;
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

      if (! Schema::hasTable('shopee_flexi_shops')) {
          return $summary;
      }

      $days = max(1, min(15, $days ?? (int) config('services.shopee_order_poll.days', 2)));
      $timeTo = time();
      $timeFrom = $timeTo - ($days * 86_400);

      $query = ShopeeFlexiShop::query()
          ->where('direct_order_ingest', true)
          ->where('shop_id', '>', 0);

      if ($onlyShopId !== null && $onlyShopId > 0) {
          $query->where('shop_id', $onlyShopId);
      }

      $syncRows = $query->orderBy('shop_id')->get();
      $summary['shops'] = $syncRows->count();

      $statuses = $this->pollStatuses();

      foreach ($syncRows as $flexiRow) {
          $shopId = (int) $flexiRow->shop_id;
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

                      $ingestResult = $this->pullAndIngestOrder(
                          $shopId,
                          $orderSn,
                          (string) $status,
                          isset($row['update_time']) ? (int) $row['update_time'] : $timeTo,
                      );

                      if (($ingestResult['error'] ?? null) !== null) {
                          $summary['errors'][] = "Shop {$shopId} order {$orderSn}: ".$ingestResult['error'];
                          continue;
                      }

                      if ($ingestResult['ingested']) {
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

          if (Schema::hasColumn('shopee_flexi_shops', 'last_order_poll_at')) {
              $flexiRow->forceFill(['last_order_poll_at' => Carbon::now()])->save();
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
   * Fetch one order from Shopee Open API and run the same ingest as poll (shadow, jubelio placeholder, unified webhook).
   *
   * Does not require {@see enabled()} or {@see ShopeeFlexiShop} — use for FLEXI/MY when Console push never arrives.
   *
   * @return array{
   *   shop_id: int,
   *   order_sn: string,
   *   order_status: string|null,
   *   ingested: bool,
   *   shadow: bool,
   *   jubelio_placeholder: bool,
   *   webhook_event: bool,
   *   error: string|null,
   * }
   */
  public function pullAndIngestOrder(int $shopId, string $orderSn, ?string $statusHint = null, ?int $updateTime = null): array
  {
      $orderSn = trim($orderSn);
      $result = [
          'shop_id' => $shopId,
          'order_sn' => $orderSn,
          'order_status' => null,
          'ingested' => false,
          'shadow' => false,
          'jubelio_placeholder' => false,
          'webhook_event' => false,
          'error' => null,
      ];

      if ($shopId <= 0 || $orderSn === '') {
          $result['error'] = 'shop_id dan ordersn wajib.';

          return $result;
      }

      if (! $this->shopeeApi->hasShopAuthorizationFor($shopId)) {
          $result['error'] = "Shop {$shopId}: belum diotorisasi di Shopee Admin OAuth.";

          return $result;
      }

      $status = $statusHint !== null && trim($statusHint) !== '' ? trim($statusHint) : 'UNKNOWN';
      $updateTime = $updateTime ?? time();

      $detail = $this->shopeeApi->getOrderDetail(
          $orderSn,
          $shopId,
          'order_status,item_list,package_list,total_amount',
      );
      if ($detail === null) {
          $result['error'] = 'get_order_detail gagal — cek API error log atau ordersn/shop_id.';

          return $result;
      }

      $detailRow = is_array($detail) ? ($detail['response']['order_list'][0] ?? null) : null;
      if (! is_array($detailRow)) {
          $result['error'] = 'Order tidak ditemukan di respons Shopee (order_list kosong).';

          return $result;
      }

      $status = (string) ($detailRow['order_status'] ?? $status);
      $updateTime = isset($detailRow['update_time']) ? (int) $detailRow['update_time'] : $updateTime;

      $pushData = [
          'ordersn' => $orderSn,
          'order_sn' => $orderSn,
          'status' => $status,
          'update_time' => $updateTime,
      ];
      if (isset($detailRow['item_list']) && is_array($detailRow['item_list'])) {
          $pushData['item_list'] = $detailRow['item_list'];
      }
      $pushData['order_detail'] = $detailRow;

      $fullPayload = [
          'code' => self::POLL_PUSH_CODE,
          'shop_id' => $shopId,
          'timestamp' => $updateTime,
          'data' => $pushData,
          'source' => 'shopee_pull',
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

      $result['order_status'] = $status;
      $result['shadow'] = (bool) $ingestResult['shadow'];
      $result['jubelio_placeholder'] = (bool) $ingestResult['jubelio_placeholder'];
      $result['webhook_event'] = (bool) $ingestResult['webhook_event'];
      $result['ingested'] = $result['shadow'] || $result['jubelio_placeholder'] || $result['webhook_event'];

      return $result;
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