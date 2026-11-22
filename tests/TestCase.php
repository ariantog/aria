<?php

namespace Tests;

use App\Models\Jubelioorder;
use App\Support\ItemPricing;
use App\Support\ItemProductTitle;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel 13 {@see PreventRequestForgery} treats Sec-Fetch-Site: same-origin as a valid origin.
     * Feature tests normally bypass CSRF via runningUnitTests(); use this for glue tests that disable
     * that bypass or exercise full browser-like headers.
     */
    protected function withSameOriginSite(): static
    {
        return $this->withHeader('Sec-Fetch-Site', 'same-origin');
    }

    protected function tearDown(): void
    {
        Jubelioorder::clearPayloadCache();
        ItemPricing::flushRequestCache();
        ItemProductTitle::flushRequestCache();

        parent::tearDown();
    }
}
