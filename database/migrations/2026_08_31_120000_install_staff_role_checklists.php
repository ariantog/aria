<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_roles')) {
            Schema::create('staff_roles', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('checklist_templates')) {
            Schema::create('checklist_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('staff_role_id');
                $table->string('frequency', 16);
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('route_name', 128)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['staff_role_id', 'frequency'], 'chk_tpl_role_freq_idx');
            });
        }

        if (! Schema::hasTable('staff_role_user')) {
            Schema::create('staff_role_user', function (Blueprint $table) {
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('staff_role_id');
                $table->timestamps();

                $table->primary(['user_id', 'staff_role_id'], 'staff_role_user_pk');
                $table->index('staff_role_id', 'staff_role_user_role_idx');
            });
        }

        if (! Schema::hasTable('checklist_completions')) {
            Schema::create('checklist_completions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('checklist_template_id');
                $table->string('period_key', 16);
                $table->timestamp('completed_at')->useCurrent();
                $table->timestamps();

                $table->unique(
                    ['user_id', 'checklist_template_id', 'period_key'],
                    'chk_completion_uq'
                );
                $table->index(['user_id', 'period_key'], 'chk_completion_user_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_completions');
        Schema::dropIfExists('staff_role_user');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('staff_roles');
    }
};
