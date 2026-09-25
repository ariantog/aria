<?php

namespace Tests;

use App\Models\Jubelioorder;
use App\Support\ItemPricing;
use App\Support\ItemProductTitle;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Jubelioorder::clearPayloadCache();
        ItemPricing::flushRequestCache();
        ItemProductTitle::flushRequestCache();

        parent::tearDown();
    }
}
