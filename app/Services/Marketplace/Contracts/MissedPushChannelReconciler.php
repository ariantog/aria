<?php

namespace App\Services\Marketplace\Contracts;

interface MissedPushChannelReconciler
{
    public function channel(): string;

    public function enabled(): bool;

    /**
     * @return array{
     *   shops: int,
     *   orders_seen: int,
     *   gaps_queued: int,
     *   ingested: int,
     *   skipped: int,
     *   errors: list<string>
     * }
     */
    public function reconcile(?int $onlyShopId = null, ?int $days = null, bool $ingestGaps = true): array;
}