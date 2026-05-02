<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyJubelioData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:legacy-jubelio 
                            {table? : Specific table to import (jubelioorders, jubelioreturns, jubeliosyncs, logjubelios)}
                            {--latest : Import only the 10 latest records per table} 
                            {--truncate : Clear existing data before importing}
                            {--force-pending : Force all imported jubelioorders to status 0 (Pending)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Jubelio related data from legacy database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $allTables = [
            'jubelioorders',
            'jubelioreturns',
            'jubeliosyncs',
            'logjubelios',
        ];

        // Default to only jubelioorders if no argument provided
        $targetTable = $this->argument('table') ?: 'jubelioorders';
        $tables = [$targetTable];

        // Validate table name
        if ($targetTable && ! in_array($targetTable, $allTables)) {
            $this->error("Invalid table name: {$targetTable}. Available: ".implode(', ', $allTables));

            return;
        }

        foreach ($tables as $table) {
            $this->info("Importing table: {$table}");

            if ($this->option('truncate')) {
                DB::table($table)->truncate();
                $this->warn("Table {$table} truncated.");
            }

            $query = DB::connection('core_legacy')->table($table);

            if ($this->option('latest')) {
                $this->info('Fetching 10 latest records...');
                $rows = $query->orderBy('id', 'desc')->limit(10)->get();
                $data = $this->prepareData($table, $rows);
                DB::table($table)->insertOrIgnore($data);
                $this->info("Imported 10 latest records for {$table} (skipped duplicates).");
            } else {
                $count = $query->count();
                $this->info("Total records to import: {$count}");

                $bar = $this->output->createProgressBar($count);
                $bar->start();

                $query->orderBy('id', 'asc')->chunk(1000, function ($rows) use ($table, $bar) {
                    $data = $this->prepareData($table, $rows);
                    DB::table($table)->insertOrIgnore($data);
                    $bar->advance(count($rows));
                });

                $bar->finish();
                $this->newLine();
                $this->info("Finished importing {$table}");
            }
        }

        $this->info('Legacy Jubelio data import process completed.');
    }

    /**
     * Prepare and potentially override data before insertion.
     */
    protected function prepareData(string $table, $rows): array
    {
        $data = json_decode(json_encode($rows), true);

        // Force Pending state for jubelioorders if flag is present
        if ($table === 'jubelioorders' && $this->option('force-pending')) {
            foreach ($data as &$row) {
                $row['status'] = 0;
                $row['run_count'] = 0;
                $row['error_type'] = null;
                $row['error'] = null;
                $row['execute_by'] = null;
            }
        }

        return $data;
    }
}
