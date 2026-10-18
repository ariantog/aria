<?php

use App\Services\Restock\RestockRecommendationApplyService;

it('suggests restock qty as ceiling of monthly net with minimum one', function () {
    expect(RestockRecommendationApplyService::suggestedQtyFromMonthlyRate(0))->toBe(0)
        ->and(RestockRecommendationApplyService::suggestedQtyFromMonthlyRate(0.2))->toBe(1)
        ->and(RestockRecommendationApplyService::suggestedQtyFromMonthlyRate(30.1))->toBe(31);
});

it('suggests eight-week cover minus on-hand stock', function () {
    expect(RestockRecommendationApplyService::suggestedQtyEightWeekCover(0, 90, 0))->toBe(0)
        ->and(RestockRecommendationApplyService::suggestedQtyEightWeekCover(981, 90, 0))->toBe(610)
        ->and(RestockRecommendationApplyService::suggestedQtyEightWeekCover(486, 90, 9))->toBe(293);
});
