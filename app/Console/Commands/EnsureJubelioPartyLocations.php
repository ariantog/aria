<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\Jubeliosync;
use App\Services\LocationAccessService;
use Illuminate\Console\Command;

class EnsureJubelioPartyLocations extends Command
{
    protected $signature = 'jubelio:ensure-party-locations';

    protected $description = 'Link Jubelio sync warehouse/customer customers to locations so their transactions appear in the list';

    public function handle(LocationAccessService $locationAccessService): int
    {
        $pairs = Jubeliosync::query()
            ->where('warehouse_id', '>', 0)
            ->where('customer_id', '>', 0)
            ->select('warehouse_id', 'customer_id')
            ->distinct()
            ->get();

        $updated = 0;

        foreach ($pairs as $pair) {
            $warehouse = Addrbook::find($pair->warehouse_id);
            $customer = Addrbook::find($pair->customer_id);

            if (! $warehouse || ! $customer) {
                continue;
            }

            $locationAccessService->ensureJubelioPartyLocations($warehouse, $customer);
            $updated++;
        }

        $this->info("Updated location links for {$updated} Jubelio warehouse/customer pair(s).");

        return self::SUCCESS;
    }
}
