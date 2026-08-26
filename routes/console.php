<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Single Laravel scheduler entry; task list + frequencies live in scheduled_tasks (Cron Manager).
Schedule::command('app:dispatch-scheduled-tasks')
    ->everyMinute()
    ->withoutOverlapping(55);
