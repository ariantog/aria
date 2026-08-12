<?php

/**
 * One-shot bulk rename: L12 greenfield schema -> production schema names.
 * Run from repo root: php scripts/rename-production-schema.php
 */

$root = dirname(__DIR__);

$dirs = ['app', 'database', 'resources', 'routes', 'tests', 'doc'];
$extensions = ['php', 'blade.php', 'md'];

$tableReplacements = [
    'deleted_transaction_details' => 'deleted_details',
    'deleted_transactions' => 'deleted',
    'addrbook_dailies' => 'customer_class',
    'addrbook_stats' => 'customerstat',
    'addrbook_location' => 'location_customer',
    'warehouse_items' => 'warehouse_item',
    'item_groups' => 'item_group',
    'borongan_details' => 'prod_borongandetail',
    'borongans' => 'prod_borongan',
    'produksis' => 'prod_produksi',
    'addrbooks' => 'customers',
    "'workers'" => "'prod_worker'",
    '"workers"' => '"prod_worker"',
    'Schema::create(\'workers\'' => 'Schema::create(\'prod_worker\'',
    'Schema::table(\'workers\'' => 'Schema::table(\'prod_worker\'',
    'Schema::hasTable(\'workers\'' => 'Schema::hasTable(\'prod_worker\'',
    'Schema::dropIfExists(\'workers\'' => 'Schema::dropIfExists(\'prod_worker\'',
    '->on(\'workers\')' => '->on(\'prod_worker\')',
    'from workers' => 'from prod_worker',
    'join workers' => 'join prod_worker',
    'FROM workers' => 'FROM prod_worker',
    'JOIN workers' => 'JOIN prod_worker',
];

$columnReplacements = [
    'invoice_number' => 'invoice',
    'due_date' => 'due',
    'tax_amount' => 'ppn',
    'grand_total' => 'real_total',
    'member_id' => 'memberId',
    'is_active' => 'active',
];

$files = [];
foreach ($dirs as $dir) {
    $path = $root.'/'.$dir;
    if (! is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        foreach ($extensions as $ext) {
            if (str_ends_with($file->getPathname(), '.'.$ext)) {
                $files[] = $file->getPathname();
                break;
            }
        }
    }
}

// .env.example at root
$files[] = $root.'/.env.example';

$changed = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    foreach ($tableReplacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    foreach ($columnReplacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    // customer_class / customerstat foreign keys
    $content = str_replace('addrbook_id', 'customer_id', $content);

    if ($content !== $original) {
        file_put_contents($file, $content);
        $changed++;
        echo basename(dirname($file)).'/'.basename($file)."\n";
    }
}

echo "\nUpdated {$changed} files.\n";
