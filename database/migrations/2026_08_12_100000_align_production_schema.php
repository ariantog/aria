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
    }

    public function down(): void
    {
        // Irreversible on production — column adds are kept.
    }

    private function addrbookTable(): ?string
    {
        if (Schema::hasTable('customers')) {
            return 'customers';
        }

        if (Schema::hasTable('customers')) {
            return 'customers';
        }

        return null;
    }

    private function alignAddrbookTable(): void
    {
        $table = $this->addrbookTable();
        if (! $table) {
            return;
        }

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
        if (Schema::hasTable('warehouse_item')) {
            return 'warehouse_item';
        }

        if (Schema::hasTable('warehouse_item')) {
            return 'warehouse_item';
        }

        return null;
    }

    private function alignWarehouseItemTable(): void
    {
        $table = $this->warehouseItemTable();
        if (! $table) {
            return;
        }

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
        if (Schema::hasTable('prod_produksi')) {
            return 'prod_produksi';
        }

        if (Schema::hasTable('prod_produksi')) {
            return 'prod_produksi';
        }

        return null;
    }

    private function alignProdProduksiTable(): void
    {
        $table = $this->produksiTable();
        if (! $table) {
            return;
        }

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
            if (! Schema::hasColumn('transactions', 'deleted_at')) {
                $blueprint->softDeletes();
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
        } elseif (Schema::hasColumn('items', 'qty') && Schema::hasTable('warehouse_item')) {
            DB::statement('
                UPDATE items
                SET qty = COALESCE((
                    SELECT SUM(quantity) FROM warehouse_item wi WHERE wi.item_id = items.id
                ), 0)
                WHERE qty = 0 OR qty IS NULL
            ');
        }
    }
};
