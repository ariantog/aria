<?php

use App\Services\Jubelio\JubelioTrackingLookup;

it('resolves via order detail when list row has no awb field', function () {
    $service = Mockery::mock(\App\Services\JubelioService::class)->makePartial();
    $service->shouldReceive('resetOrdersQueryProbe')->once();
    $service->shouldReceive('fetchSalesOrdersQuery')
        ->andReturnUsing(function (int $page, int $pageSize, array $query) {
            if (($query['tracking_no'] ?? null) === 'JNE111222') {
                return [
                    'data' => [
                        [
                            'salesorder_id' => 9001,
                            'salesorder_no' => 'TT-VIA-DETAIL',
                            'store_id' => 4,
                        ],
                    ],
                ];
            }

            return ['data' => [], 'totalCount' => 0];
        });
    $service->shouldReceive('fetchReadyToPickOrders')->andReturn(['data' => [], 'totalCount' => 0]);
    $service->shouldReceive('ordersQueryFailureResult')->andReturn([
        'status' => 'not_found',
        'message' => 'Resi tidak ditemukan di Jubelio.',
    ]);
    $service->shouldReceive('fetchSalesOrder')
        ->once()
        ->with(9001)
        ->andReturn([
            'salesorder_id' => 9001,
            'salesorder_no' => 'TT-VIA-DETAIL',
            'shipments' => [['tracking_no' => 'JNE111222']],
        ]);

    $lookup = new JubelioTrackingLookup($service);
    $result = $lookup->resolve('JNE111222', 4);

    expect($result['status'])->toBe('found')
        ->and($result['salesorder_no'])->toBe('TT-VIA-DETAIL');
});

it('surfaces token missing message via resolver path', function () {
    $this->mock(\App\Services\JubelioService::class, function ($mock) {
        $mock->shouldReceive('resolveSalesOrderByTracking')
            ->once()
            ->andReturn([
                'status' => 'token_missing',
                'message' => 'Token Jubelio tidak tersedia. Periksa koneksi Jubelio di System Settings.',
            ]);
    });

    $resolver = app(\App\Services\Jubelio\JubelioResiInvoiceResolver::class);
    config(['services.jubelio.active' => true]);

    expect($resolver->resolveInvoice('tiktok', 'AWB123', 1))->toBeNull()
        ->and($resolver->lastLookupMessage())->toContain('Token Jubelio');
});
