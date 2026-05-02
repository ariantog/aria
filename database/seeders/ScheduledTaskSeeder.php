<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ScheduledTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'app:generate-stock-intelligence'],
            [
                'name' => 'Generate Stock Intelligence',
                'frequency' => 'daily',
                'is_active' => true,
                'description' => 'Generates daily stock intelligence reports.',
            ]
        );

        \App\Models\ScheduledTask::updateOrCreate(
            ['command' => 'jubelio:order-jubelio-to-aria'],
            [
                'name' => 'Sync Jubelio Orders',
                'frequency' => 'everyMinute',
                'is_active' => true,
                'description' => 'Processes pending Jubelio orders into Aria transactions.',
            ]
        );
    }
}
