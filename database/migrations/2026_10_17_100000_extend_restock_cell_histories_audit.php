<?php

use App\Support\GreenfieldMysqlSchema;
use App\Support\RestockCellHistoryAuditSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restock_cell_histories')) {
            return;
        }

        Schema::table('restock_cell_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('restock_cell_histories', 'restock_sheet_id')) {
                $table->foreignId('restock_sheet_id')->nullable()->after('restock_cell_id');
                $table->index(['restock_sheet_id', 'created_at'], 'restock_hist_sheet_created_idx');
            }

            if (! Schema::hasColumn('restock_cell_histories', 'item_id')) {
                GreenfieldMysqlSchema::legacyReferenceIdColumn($table, 'item_id', true);
                $table->index(['item_id', 'created_at'], 'restock_hist_item_created_idx');
            }

            if (! Schema::hasColumn('restock_cell_histories', 'actor_type')) {
                $table->string('actor_type', 16)->default('user')->after('action');
            }

            if (! Schema::hasColumn('restock_cell_histories', 'source')) {
                $table->string('source', 64)->nullable()->after('actor_type');
            }

        });

        if (! $this->indexExists('restock_cell_histories', 'restock_hist_created_idx')
            && Schema::hasColumn('restock_cell_histories', 'created_at')) {
            Schema::table('restock_cell_histories', function (Blueprint $table) {
                $table->index('created_at', 'restock_hist_created_idx');
            });
        }

        if (Schema::hasColumn('restock_cell_histories', 'restock_sheet_id')) {
            \App\Models\RestockCellHistory::query()
                ->whereNull('restock_sheet_id')
                ->whereNotNull('restock_cell_id')
                ->with('cell:id,restock_sheet_id,item_id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        if ($row->cell === null) {
                            continue;
                        }

                        $row->update([
                            'restock_sheet_id' => $row->cell->restock_sheet_id,
                            'item_id' => $row->cell->item_id,
                        ]);
                    }
                });
        }

        RestockCellHistoryAuditSchema::makeUserIdNullable();
        RestockCellHistoryAuditSchema::relaxRestockCellForeignKey();
    }

    public function down(): void
    {
        if (! Schema::hasTable('restock_cell_histories')) {
            return;
        }

        Schema::table('restock_cell_histories', function (Blueprint $table) {
            if (Schema::hasColumn('restock_cell_histories', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('restock_cell_histories', 'actor_type')) {
                $table->dropColumn('actor_type');
            }
            if (Schema::hasColumn('restock_cell_histories', 'item_id')) {
                $table->dropIndex('restock_hist_item_created_idx');
                $table->dropColumn('item_id');
            }
            if (Schema::hasColumn('restock_cell_histories', 'restock_sheet_id')) {
                $table->dropIndex('restock_hist_sheet_created_idx');
                $table->dropForeign(['restock_sheet_id']);
                $table->dropColumn('restock_sheet_id');
            }
            if ($this->indexExists('restock_cell_histories', 'restock_hist_created_idx')) {
                $table->dropIndex('restock_hist_created_idx');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            return collect($rows)->contains(fn ($row) => ($row->name ?? '') === $indexName);
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [$table, $indexName]
        );

        return $row !== null;
    }
};
