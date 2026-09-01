<?php

namespace Database\Seeders;

use App\Models\Addrbook;
use App\Models\Operation;
use App\Support\NewDomainBaselineWriter;
use App\Support\NewDomainChartOfAccounts;
use App\Support\NewDomainInstall;
use Illuminate\Database\Seeder;

/**
 * One starter contact per addrbook type for a new subdomain.
 *
 * Refuses to run on the current production domain (Crystal / aria.corenationactive.com).
 */
class AddrbookPlaceholderSeeder extends Seeder
{
    public function run(): void
    {
        if (! NewDomainInstall::allowsBaselineSeed()) {
            $this->command?->error('AddrbookPlaceholderSeeder refused: '.NewDomainInstall::refusalReason());

            return;
        }

        $lainLainId = Operation::query()->where('name', 'Lain-lain')->value('id');

        foreach (NewDomainChartOfAccounts::placeholders() as $row) {
            $attributes = [
                'description' => $row['description'],
                'ppn' => $row['ppn'] ?? false,
            ];

            if ((int) $row['type'] === Addrbook::TYPE_ACCOUNT && $lainLainId) {
                $attributes['operation_id'] = (int) $lainLainId;
            }

            NewDomainBaselineWriter::ensureAddrbook($row['name'], (int) $row['type'], $attributes);
        }

        $this->command?->info('Addrbook placeholders seeded (one per type).');
    }
}
