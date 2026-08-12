<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align an existing Laravel 10 production database with Aria L12 expectations.
 *
 * Safe to run on an existing Laravel 10 production database: every change is guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->alignAddrbookTable();
        $this->alignItemsTable();
        $this->alignWarehouseItemTable();
        $this->alignProdProduksiTable();
        $this->alignTransactionsTable();
        $this->alignSessionsTable();
        $this->alignSettingsTable();
        $this->alignUsersTable();
        $this->alignOperationsTable();
        $this->alignTagsTable();
        $this->alignWarehouseComparesTable();
        $this->alignSoftDeleteColumns();
    }

    public function down(): void
    {
        // Irreversible on production — column adds are kept.
    }

    /**
     * L10 schemas often use DEFAULT '0000-00-00 00:00:00' on timestamps, which makes
     * any subsequent ALTER fail under strict SQL mode. Normalize before adding columns.
     *
     * Must fix updated_at before created_at — MySQL validates all column defaults on each ALTER.
     */
    private function fixLegacyZeroDateTimestamps(string $table): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable($table)) {
            return;
        }

        $columns = array_values(array_filter(
            ['updated_at', 'created_at'],
            fn (string $column) => Schema::hasColumn($table, $column),
        ));

        if ($columns === []) {
            return;
        }

        DB::statement('SET @aria_old_sql_mode = @@SESSION.sql_mode');
        DB::statement("SET SESSION sql_mode = REPLACE(REPLACE(@aria_old_sql_mode, 'NO_ZERO_DATE', ''), 'NO_ZERO_IN_DATE', '')");

        try {
            foreach ($columns as $column) {
                DB::statement("
                    UPDATE `{$table}`
                    SET `{$column}` = NULL
                    WHERE `{$column}` = '0000-00-00 00:00:00'
                       OR `{$column}` = '0000-00-00'
                ");
            }

            foreach ($columns as $column) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NULL DEFAULT NULL");
            }
        } finally {
            DB::statement('SET SESSION sql_mode = @aria_old_sql_mode');
        }
    }

    private function addrbookTable(): ?string
    {
        return Schema::hasTable('customers') ? 'customers' : null;
    }

    private function alignAddrbookTable(): void
    {
        $table = $this->addrbookTable();
        if (! $table) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps($table);

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if ($table === 'customers' && ! Schema::hasColumn($table, 'operation_id')) {
                $blueprint->unsignedBigInteger('operation_id')->nullable()->after('ppn');
            }
            if (! Schema::hasColumn($table, 'arrangement_enabled')) {
                $blueprint->boolean('arrangement_enabled')->default(false);
            }
            if ($table === 'customers' && ! Schema::hasColumn($table, 'contact_person')) {
                $blueprint->string('contact_person')->nullable();
            }
        });
    }

    private function alignItemsTable(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps('items');

        Schema::table('items', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('items', 'qty')) {
                $blueprint->decimal('qty', 15, 2)->default(0)->after('cost');
            }
            if (! Schema::hasColumn('items', 'legacy_code')) {
                $blueprint->string('legacy_code')->nullable()->after('code');
            }
            if (! Schema::hasColumn('items', 'url')) {
                $blueprint->string('url')->nullable();
            }
            if (! Schema::hasColumn('items', 'image_path')) {
                $blueprint->string('image_path', 2048)->nullable();
            }
            if (! Schema::hasColumn('items', 'restock_urgent_threshold')) {
                $blueprint->unsignedInteger('restock_urgent_threshold')->nullable();
            }
        });
    }

    private function warehouseItemTable(): ?string
    {
        return Schema::hasTable('warehouse_item') ? 'warehouse_item' : null;
    }

    private function alignWarehouseItemTable(): void
    {
        $table = $this->warehouseItemTable();
        if (! $table) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps($table);

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (! Schema::hasColumn($table, 'warehouse_type')) {
                $blueprint->string('warehouse_type')->default('2');
            }
            if (! Schema::hasColumn($table, 'note')) {
                $blueprint->text('note')->nullable();
            }
            if (! Schema::hasColumn($table, 'created_at')) {
                $blueprint->timestamps();
            }
        });
    }

    private function produksiTable(): ?string
    {
        return Schema::hasTable('prod_produksi') ? 'prod_produksi' : null;
    }

    private function alignProdProduksiTable(): void
    {
        $table = $this->produksiTable();
        if (! $table) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps($table);

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $columns = [
                'user_id' => fn (Blueprint $b) => $b->unsignedInteger('user_id')->nullable(),
                'qc_id' => fn (Blueprint $b) => $b->unsignedBigInteger('qc_id')->nullable(),
                'qc_date' => fn (Blueprint $b) => $b->dateTime('qc_date')->nullable(),
                'pritil_id' => fn (Blueprint $b) => $b->unsignedBigInteger('pritil_id')->nullable(),
                'pritil_date' => fn (Blueprint $b) => $b->dateTime('pritil_date')->nullable(),
                'original_id' => fn (Blueprint $b) => $b->unsignedBigInteger('original_id')->nullable(),
                'transaction_id' => fn (Blueprint $b) => $b->unsignedBigInteger('transaction_id')->nullable(),
            ];

            foreach ($columns as $name => $callback) {
                if (! Schema::hasColumn($table, $name)) {
                    $callback($blueprint);
                }
            }
        });
    }

    private function alignTransactionsTable(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps('transactions');

        Schema::table('transactions', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('transactions', 'real_total')) {
                $blueprint->decimal('real_total', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('transactions', 'notes')) {
                $blueprint->text('notes')->nullable();
            }
            if (! Schema::hasColumn('transactions', 'reference_number')) {
                $blueprint->string('reference_number')->nullable();
            }
        });

        if (Schema::hasColumn('items', 'qty') && Schema::hasTable('warehouse_item')) {
            DB::statement('
                UPDATE items
                SET qty = COALESCE((
                    SELECT SUM(quantity) FROM warehouse_item wi WHERE wi.item_id = items.id
                ), 0)
                WHERE qty = 0 OR qty IS NULL
            ');
        }
    }

    /**
     * L10 sessions table only has id, payload, last_activity.
     * Laravel 12 database sessions also need user_id, ip_address, user_agent.
     */
    private function alignSessionsTable(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('sessions', 'user_id')) {
                $blueprint->integer('user_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('sessions', 'ip_address')) {
                $blueprint->string('ip_address', 45)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('sessions', 'user_agent')) {
                $blueprint->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    /**
     * L10 settings: name (key), value, location_id — no id/slug/group.
     * L12 uses id, slug, group, name (label), value (JSON).
     */
    private function alignSettingsTable(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps('settings');

        if (! Schema::hasColumn('settings', 'id')) {
            DB::statement('ALTER TABLE `settings` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        }

        Schema::table('settings', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('settings', 'slug')) {
                $blueprint->string('slug', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('settings', 'group')) {
                $blueprint->string('group')->nullable()->after('id');
            }
        });

        if (Schema::hasColumn('settings', 'slug')) {
            DB::statement("UPDATE `settings` SET `slug` = `name` WHERE `slug` IS NULL OR `slug` = ''");
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `settings` MODIFY `name` VARCHAR(191) NOT NULL');
            DB::statement('ALTER TABLE `settings` MODIFY `value` TEXT NULL');
        }
    }

    private function alignUsersTable(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps('users');

        Schema::table('users', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('users', 'name')) {
                $blueprint->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('users', 'email')) {
                $blueprint->string('email')->nullable()->after('username');
            }
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $blueprint->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $blueprint->text('two_factor_secret')->nullable();
            }
            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $blueprint->text('two_factor_recovery_codes')->nullable();
            }
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $blueprint->timestamp('two_factor_confirmed_at')->nullable();
            }
        });

        if (Schema::hasColumn('users', 'name')) {
            DB::statement("UPDATE `users` SET `name` = `username` WHERE `name` IS NULL OR `name` = ''");
        }
    }

    private function alignOperationsTable(): void
    {
        if (! Schema::hasTable('operations')) {
            return;
        }

        Schema::table('operations', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('operations', 'created_at')) {
                $blueprint->timestamps();
            }
            if (! Schema::hasColumn('operations', 'deleted_at')) {
                $blueprint->softDeletes();
            }
        });
    }

    private function alignTagsTable(): void
    {
        if (! Schema::hasTable('tags')) {
            return;
        }

        $this->fixLegacyZeroDateTimestamps('tags');

        Schema::table('tags', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('tags', 'created_at')) {
                $blueprint->timestamps();
            }
        });
    }

    private function alignWarehouseComparesTable(): void
    {
        if (! Schema::hasTable('warehouse_compares')) {
            return;
        }

        if (Schema::hasColumn('warehouse_compares', 'werehouse_id') && ! Schema::hasColumn('warehouse_compares', 'warehouse_id')) {
            DB::statement('ALTER TABLE `warehouse_compares` CHANGE `werehouse_id` `warehouse_id` INT(11) NOT NULL');
        }
    }

    private function alignSoftDeleteColumns(): void
    {
        foreach (['karyawans', 'cutis'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }
};
