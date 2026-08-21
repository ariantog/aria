<?php

namespace Tests;

use App\Models\Jubelioorder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Jubelioorder::clearPayloadCache();

        parent::tearDown();
    }
}
