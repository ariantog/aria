<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'legacy_code')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('legacy_code')->nullable()->after('code');
                $table->index('legacy_code');
            });
        } elseif (! $this->legacyCodeIndexExists()) {
            Schema::table('items', function (Blueprint $table) {
                $table->index('legacy_code');
            });
        }

        // Preserve pre-migration SKUs for Jubelio + barcode lookups after code format changes.
        if (Schema::hasColumn('items', 'legacy_code')) {
            DB::table('items')
                ->whereNull('legacy_code')
                ->where('code', '!=', '')
                ->update(['legacy_code' => DB::raw('code')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('items', 'legacy_code')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            if ($this->legacyCodeIndexExists()) {
                $table->dropIndex(['legacy_code']);
            }
            $table->dropColumn('legacy_code');
        });
    }

    private function legacyCodeIndexExists(): bool
    {
        $indexes = Schema::getIndexes('items');

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === 'items_legacy_code_index') {
                return true;
            }
        }

        return false;
    }
};
