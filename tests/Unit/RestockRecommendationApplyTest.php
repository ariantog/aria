<?php

use App\Services\Restock\RestockRecommendationApplyService;

it('suggests restock qty as ceiling of monthly net with minimum one', function () {
    expect(RestockRecommendationApplyService::suggestedQtyFromMonthlyRate(0))->toBe(0)
        ->and(RestockRecommendationApplyService::suggestedQtyFromMonthlyRate(0.2))->toBe(1)
        ->and(RestockRecommendationApplyService::suggestedQtyFromMonthlyRate(30.1))->toBe(31);
});
