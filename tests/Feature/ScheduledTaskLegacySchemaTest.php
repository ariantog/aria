<?php

use App\Models\ScheduledTask;
use Illuminate\Support\Facades\Schema;

it('loads active scheduled tasks from legacy is_active column', function () {
    if (! Schema::hasTable('scheduled_tasks')) {
        $this->markTestSkipped('scheduled_tasks table missing');
    }

    if (Schema::hasColumn('scheduled_tasks', 'active')) {
        Schema::table('scheduled_tasks', function ($table) {
            $table->renameColumn('active', 'is_active');
        });
    }

    ScheduledTask::query()->delete();

    $task = ScheduledTask::create([
        'name' => 'Sync Jubelio Orders',
        'command' => 'jubelio:order-jubelio-to-aria',
        'frequency' => 'everyMinute',
        'description' => 'Processes pending Jubelio orders.',
    ]);

    $task->update(['active' => true]);

    expect(ScheduledTask::activeColumn())->toBe('is_active');
    expect(ScheduledTask::activeTasksQuery()->count())->toBe(1);
    expect(ScheduledTask::activeTasksQuery()->first()->command)->toBe('jubelio:order-jubelio-to-aria');
});

it('align migration renames legacy scheduled_tasks columns to l12 names', function () {
    if (! Schema::hasTable('scheduled_tasks')) {
        $this->markTestSkipped('scheduled_tasks table missing');
    }

    if (Schema::hasColumn('scheduled_tasks', 'active')) {
        Schema::table('scheduled_tasks', function ($table) {
            $table->renameColumn('active', 'is_active');
        });
    }

    if (Schema::hasColumn('scheduled_tasks', 'frequency') && ! Schema::hasColumn('scheduled_tasks', 'expression')) {
        Schema::table('scheduled_tasks', function ($table) {
            $table->renameColumn('frequency', 'expression');
        });
    }

    expect(Schema::hasColumn('scheduled_tasks', 'is_active'))->toBeTrue();
    expect(Schema::hasColumn('scheduled_tasks', 'expression'))->toBeTrue();

    $migration = require database_path('migrations/2026_08_13_140000_align_scheduled_tasks_table_for_l12.php');
    $migration->up();

    expect(Schema::hasColumn('scheduled_tasks', 'active'))->toBeTrue();
    expect(Schema::hasColumn('scheduled_tasks', 'frequency'))->toBeTrue();
    expect(Schema::hasColumn('scheduled_tasks', 'is_active'))->toBeFalse();
    expect(Schema::hasColumn('scheduled_tasks', 'expression'))->toBeFalse();
});
