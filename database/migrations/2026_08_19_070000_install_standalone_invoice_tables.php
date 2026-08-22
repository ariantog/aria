<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create or align standalone invoice tables on an existing production database.
 *
 * Every CREATE is guarded with Schema::hasTable(). Column changes use
 * Schema::hasColumn(). Safe to run when tables already exist from a partial
 * or older migration attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->installStandaloneInvoicesTable();
        $this->installStandaloneInvoiceLinesTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('standalone_invoice_lines');
        Schema::dropIfExists('standalone_invoices');
    }

    private function installStandaloneInvoicesTable(): void
    {
        if (! Schema::hasTable('standalone_invoices')) {
            Schema::create('standalone_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('number');
                $table->date('date');
                $table->text('recipient');
                $table->unsignedInteger('sender_addrbook_id')->nullable()->index();
                $table->string('template', 32)->default('classic');
                $table->string('preset_id', 64)->nullable();
                $table->text('terms_of_payment')->nullable();
                $table->text('pay_to')->nullable();
                $table->string('signatory_name')->nullable();
                $table->string('signature_path')->nullable();
                $table->string('logo_path')->nullable();
                $table->decimal('total_qty', 16, 4)->default(0);
                $table->decimal('subtotal', 16, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->timestamps();

                $table->index('date');
                $table->index('number');
            });

            return;
        }

        $this->alignStandaloneInvoicesTable();
    }

    private function alignStandaloneInvoicesTable(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::disableForeignKeyConstraints();
            $this->dropForeignKeyIfExists('standalone_invoices', 'standalone_invoices_recipient_addrbook_id_foreign');
            $this->dropForeignKeyIfExists('standalone_invoices', 'standalone_invoices_sender_addrbook_id_foreign');
            $this->dropForeignKeyIfExists('standalone_invoices', 'standalone_invoices_user_id_foreign');
        }

        if (Schema::hasColumn('standalone_invoices', 'recipient_addrbook_id')) {
            Schema::table('standalone_invoices', function (Blueprint $table) {
                $table->dropColumn('recipient_addrbook_id');
            });
        }

        if (Schema::hasColumn('standalone_invoices', 'recipient_name') && ! Schema::hasColumn('standalone_invoices', 'recipient')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE standalone_invoices CHANGE recipient_name recipient TEXT NOT NULL');
            } else {
                Schema::table('standalone_invoices', function (Blueprint $table) {
                    $table->text('recipient')->nullable();
                });
                DB::table('standalone_invoices')->update([
                    'recipient' => DB::raw('recipient_name'),
                ]);
                Schema::table('standalone_invoices', function (Blueprint $table) {
                    $table->dropColumn('recipient_name');
                });
            }
        }

        Schema::table('standalone_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('standalone_invoices', 'recipient')) {
                $table->text('recipient');
            }
            if (! Schema::hasColumn('standalone_invoices', 'sender_addrbook_id')) {
                $table->unsignedInteger('sender_addrbook_id')->nullable()->index();
            }
            if (! Schema::hasColumn('standalone_invoices', 'template')) {
                $table->string('template', 32)->default('classic');
            }
            if (! Schema::hasColumn('standalone_invoices', 'preset_id')) {
                $table->string('preset_id', 64)->nullable()->after('template');
            }
            if (! Schema::hasColumn('standalone_invoices', 'terms_of_payment')) {
                $table->text('terms_of_payment')->nullable();
            }
            if (! Schema::hasColumn('standalone_invoices', 'pay_to')) {
                $table->text('pay_to')->nullable();
            }
            if (! Schema::hasColumn('standalone_invoices', 'signatory_name')) {
                $table->string('signatory_name')->nullable();
            }
            if (! Schema::hasColumn('standalone_invoices', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('signatory_name');
            }
            if (! Schema::hasColumn('standalone_invoices', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('signature_path');
            }
            if (! Schema::hasColumn('standalone_invoices', 'total_qty')) {
                $table->decimal('total_qty', 16, 4)->default(0);
            }
            if (! Schema::hasColumn('standalone_invoices', 'subtotal')) {
                $table->decimal('subtotal', 16, 2)->default(0);
            }
            if (! Schema::hasColumn('standalone_invoices', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('standalone_invoices', 'user_id')) {
                $table->unsignedInteger('user_id')->nullable()->index();
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function installStandaloneInvoiceLinesTable(): void
    {
        if (Schema::hasTable('standalone_invoice_lines')) {
            return;
        }

        Schema::create('standalone_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('standalone_invoice_id');
            $table->unsignedSmallInteger('line_order')->default(0);
            $table->string('description');
            $table->decimal('quantity', 16, 4)->default(0);
            $table->decimal('price', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->timestamps();

            $table->index('standalone_invoice_id');
        });
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
