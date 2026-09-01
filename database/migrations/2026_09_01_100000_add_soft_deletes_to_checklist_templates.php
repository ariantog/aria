<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checklist_templates')) {
            return;
        }

        Schema::table('checklist_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('checklist_templates', 'catalog_key')) {
                $table->string('catalog_key', 80)->nullable()->unique('chk_tpl_catalog_key_uq');
            }
            if (! Schema::hasColumn('checklist_templates', 'route_query')) {
                $table->string('route_query', 255)->nullable();
            }
            if (! Schema::hasColumn('checklist_templates', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('checklist_templates')) {
            return;
        }

        Schema::table('checklist_templates', function (Blueprint $table) {
            if (Schema::hasColumn('checklist_templates', 'catalog_key')) {
                $table->dropUnique('chk_tpl_catalog_key_uq');
                $table->dropColumn('catalog_key');
            }
            if (Schema::hasColumn('checklist_templates', 'route_query')) {
                $table->dropColumn('route_query');
            }
            if (Schema::hasColumn('checklist_templates', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
