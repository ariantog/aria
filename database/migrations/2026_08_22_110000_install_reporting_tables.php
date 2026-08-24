<?php

use App\Support\ProductionMysqlCompat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe install for financial reporting schema.
 *
 * Run on production individually (never bare migrate:fresh):
 *
 *   php artisan migrate --path=database/migrations/2026_08_22_110000_install_reporting_tables.php --force
 *
 * Rules applied:
 * - Guarded CREATE / ALTER (hasTable / hasColumn)
 * - Legacy customer PKs are INT(11) — all customer references use integer(), never foreignId()
 * - New NOT NULL columns on customers get DEFAULT values
 * - No DROP on production tables; additive changes only
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CUSTOMER_INT_COLUMNS = [
        'reporting_entity_banks' => ['bank_id'],
        'reporting_channel_banks' => ['customer_id', 'bank_id'],
        'reporting_warehouse_fulfillment' => ['warehouse_id', 'customer_id'],
        'reporting_ledger_roles' => ['customer_id'],
        'reporting_tax_accounts' => ['legacy_ledger_id'],
        'ledger_merge_maps' => ['old_customer_id', 'new_customer_id'],
    ];

    public function up(): void
    {
        $this->installReportingEntitiesTable();
        $this->installReportingEntityBanksTable();
        $this->installReportingChannelBanksTable();
        $this->installReportingWarehouseFulfillmentTable();
        $this->installReportingLedgerRolesTable();
        $this->installReportingTaxAccountsTable();
        $this->installLedgerMergeMapsTable();
        $this->alignCustomerReportingColumns();
        $this->alignOperationsReportSlug();
        $this->alignCustomerReferenceColumns();
    }

    public function down(): void
    {
        // Production: never drop legacy-adjacent tables from down().
        Schema::dropIfExists('ledger_merge_maps');
        Schema::dropIfExists('reporting_tax_accounts');
        Schema::dropIfExists('reporting_ledger_roles');
        Schema::dropIfExists('reporting_warehouse_fulfillment');
        Schema::dropIfExists('reporting_channel_banks');
        Schema::dropIfExists('reporting_entity_banks');
        Schema::dropIfExists('reporting_entities');
    }

    private function installReportingEntitiesTable(): void
    {
        if (Schema::hasTable('reporting_entities')) {
            return;
        }

        Schema::create('reporting_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_pkp')->default(false);
            $table->string('npwp', 20)->nullable();
            $table->decimal('modal', 15, 2)->nullable();
            $table->decimal('laba_ditahan_awal', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function installReportingEntityBanksTable(): void
    {
        if (! Schema::hasTable('reporting_entity_banks')) {
            Schema::create('reporting_entity_banks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporting_entity_id')->constrained('reporting_entities')->cascadeOnDelete();
                $table->integer('bank_id');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique('bank_id');
                $table->index('bank_id');
            });

            return;
        }

        $this->ensureIntegerColumn('reporting_entity_banks', 'bank_id', false);
    }

    private function installReportingChannelBanksTable(): void
    {
        if (! Schema::hasTable('reporting_channel_banks')) {
            Schema::create('reporting_channel_banks', function (Blueprint $table) {
                $table->id();
                $table->integer('customer_id');
                $table->integer('bank_id');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique('customer_id');
                $table->index(['customer_id', 'bank_id']);
            });

            return;
        }

        $this->ensureIntegerColumn('reporting_channel_banks', 'customer_id', false);
        $this->ensureIntegerColumn('reporting_channel_banks', 'bank_id', false);
    }

    private function installReportingWarehouseFulfillmentTable(): void
    {
        if (! Schema::hasTable('reporting_warehouse_fulfillment')) {
            Schema::create('reporting_warehouse_fulfillment', function (Blueprint $table) {
                $table->id();
                $table->integer('warehouse_id');
                $table->integer('customer_id');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['warehouse_id', 'customer_id']);
            });

            return;
        }

        $this->ensureIntegerColumn('reporting_warehouse_fulfillment', 'warehouse_id', false);
        $this->ensureIntegerColumn('reporting_warehouse_fulfillment', 'customer_id', false);
    }

    private function installReportingLedgerRolesTable(): void
    {
        if (! Schema::hasTable('reporting_ledger_roles')) {
            Schema::create('reporting_ledger_roles', function (Blueprint $table) {
                $table->id();
                $table->integer('customer_id')->unique();
                $table->string('role', 40);
                $table->timestamps();
            });

            return;
        }

        $this->ensureIntegerColumn('reporting_ledger_roles', 'customer_id', false);
    }

    private function installReportingTaxAccountsTable(): void
    {
        if (! Schema::hasTable('reporting_tax_accounts')) {
            Schema::create('reporting_tax_accounts', function (Blueprint $table) {
                $table->id();
                $table->integer('legacy_ledger_id')->unique();
                $table->foreignId('reporting_entity_id')->constrained('reporting_entities')->cascadeOnDelete();
                $table->string('tax_type', 30);
                $table->timestamps();
            });

            return;
        }

        $this->ensureIntegerColumn('reporting_tax_accounts', 'legacy_ledger_id', false);
    }

    private function installLedgerMergeMapsTable(): void
    {
        if (! Schema::hasTable('ledger_merge_maps')) {
            Schema::create('ledger_merge_maps', function (Blueprint $table) {
                $table->id();
                $table->integer('old_customer_id')->unique();
                $table->integer('new_customer_id');
                $table->timestamps();

                $table->index('new_customer_id');
            });

            return;
        }

        $this->ensureIntegerColumn('ledger_merge_maps', 'old_customer_id', false);
        $this->ensureIntegerColumn('ledger_merge_maps', 'new_customer_id', false);
    }

    private function alignCustomerReportingColumns(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $alter = function () {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasColumn('customers', 'npwp')) {
                    $table->string('npwp', 20)->nullable();
                }
                if (! Schema::hasColumn('customers', 'ledger_hint')) {
                    $table->text('ledger_hint')->nullable();
                }
                if (! Schema::hasColumn('customers', 'default_bank_id')) {
                    $table->integer('default_bank_id')->nullable();
                }
                if (! Schema::hasColumn('customers', 'reporting_role')) {
                    $table->string('reporting_role', 30)->nullable();
                }
                if (! Schema::hasColumn('customers', 'is_internal_lending')) {
                    $table->boolean('is_internal_lending')->default(false);
                }
                if (! Schema::hasColumn('customers', 'is_active_in_reports')) {
                    $table->boolean('is_active_in_reports')->default(true);
                }
            });
        };

        if (ProductionMysqlCompat::isMysql()) {
            ProductionMysqlCompat::alterTable('customers', $alter);
        } else {
            $alter();
        }

        $this->dropForeignKeyIfExists('customers', 'customers_default_bank_id_foreign');
        $this->ensureIntegerColumn('customers', 'default_bank_id', true);
    }

    private function alignOperationsReportSlug(): void
    {
        if (! Schema::hasTable('operations') || Schema::hasColumn('operations', 'report_slug')) {
            return;
        }

        Schema::table('operations', function (Blueprint $table) {
            $table->string('report_slug', 40)->nullable();
        });
    }

    private function alignCustomerReferenceColumns(): void
    {
        foreach (self::CUSTOMER_INT_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $this->dropForeignKeysOnColumn($table, $column);
                $this->ensureIntegerColumn($table, $column, false);
            }
        }
    }

    private function ensureIntegerColumn(string $table, string $column, bool $nullable): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if (! ProductionMysqlCompat::isMysql()) {
            return;
        }

        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (! $row || stripos($row->COLUMN_TYPE, 'bigint') === false) {
            return;
        }

        $nullSql = ($nullable || $row->IS_NULLABLE === 'YES') ? 'NULL' : 'NOT NULL';
        $defaultSql = $this->integerDefaultClause($row, $nullable);

        ProductionMysqlCompat::alterTable($table, function () use ($table, $column, $nullSql, $defaultSql) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` INT(11) {$nullSql}{$defaultSql}");
        });
    }

    private function integerDefaultClause(object $row, bool $forceNullable): string
    {
        if ($forceNullable || $row->IS_NULLABLE === 'YES') {
            return '';
        }

        $columnDefault = $row->COLUMN_DEFAULT;
        if ($columnDefault !== null && strtoupper((string) $columnDefault) !== 'NULL') {
            return is_numeric($columnDefault)
                ? ' DEFAULT '.(int) $columnDefault
                : " DEFAULT '{$columnDefault}'";
        }

        return ' DEFAULT 0';
    }

    private function dropForeignKeysOnColumn(string $table, string $column): void
    {
        if (! ProductionMysqlCompat::isMysql()) {
            return;
        }

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($constraints as $constraint) {
            $this->dropForeignKeyIfExists($table, $constraint->CONSTRAINT_NAME);
        }
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if (! ProductionMysqlCompat::isMysql()) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
