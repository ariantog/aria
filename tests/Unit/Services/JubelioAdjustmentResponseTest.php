<?php

use App\Services\Jubelio\JubelioAdjustmentResponse;

it('treats item_adj_id as the created reference', function () {
    $parsed = JubelioAdjustmentResponse::fromHttp(200, ['item_adj_id' => 44121, 'item_adj_no' => 'ADJ-1']);

    expect($parsed->created())->toBeTrue()
        ->and($parsed->referenceId)->toBe('44121');
});

it('accepts nested data.item_adj_id and top-level id', function (int $status, mixed $json, string $expected) {
    $parsed = JubelioAdjustmentResponse::fromHttp($status, $json);

    expect($parsed->created())->toBeTrue()
        ->and($parsed->referenceId)->toBe($expected);
})->with([
    'legacy id field' => [200, ['id' => 98765], '98765'],
    'nested object' => [201, ['data' => ['item_adj_id' => 12]], '12'],
    'raw integer body' => [200, 5566, '5566'],
    'numeric string body' => [200, '7788', '7788'],
]);

it('rejects zero or negative jubelio ids', function () {
    expect(JubelioAdjustmentResponse::fromHttp(200, ['item_adj_id' => -1])->created())->toBeFalse()
        ->and(JubelioAdjustmentResponse::fromHttp(200, ['id' => 0])->created())->toBeFalse();
});

it('treats http 200 error payloads as failures instead of success', function (mixed $json, string $needle) {
    $parsed = JubelioAdjustmentResponse::fromHttp(200, $json);

    expect($parsed->failed())->toBeTrue()
        ->and($parsed->created())->toBeFalse()
        ->and($parsed->ambiguous())->toBeFalse()
        ->and($parsed->message)->toContain($needle);
})->with([
    'message only' => [['message' => 'Qty exceeds available stock'], 'Qty exceeds available stock'],
    'status failed' => [['status' => 'failed', 'message' => 'Item not found'], 'Item not found'],
    'error field' => [['error' => 'Unauthorized location'], 'Unauthorized location'],
    'listing' => [['data' => [], 'totalCount' => 0], 'daftar penyesuaian'],
]);

it('treats http errors as failures and empty 2xx as ambiguous', function () {
    $httpError = JubelioAdjustmentResponse::fromHttp(400, ['message' => 'Validation error']);
    $emptyOk = JubelioAdjustmentResponse::fromHttp(200, []);
    $statusOk = JubelioAdjustmentResponse::fromHttp(200, ['status' => 'success']);
    $noResponse = JubelioAdjustmentResponse::fromHttp(null, null);

    expect($httpError->failed())->toBeTrue()
        ->and($httpError->message)->toBe('Validation error')
        ->and($emptyOk->ambiguous())->toBeTrue()
        ->and($emptyOk->message)->toContain('tidak ada reference ID')
        ->and($statusOk->ambiguous())->toBeTrue()
        ->and($noResponse->ambiguous())->toBeTrue();
});
