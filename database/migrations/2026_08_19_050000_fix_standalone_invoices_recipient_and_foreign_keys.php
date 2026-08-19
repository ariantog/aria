<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('standalone_invoices')) {
            return;
        }

        if (Schema::hasColumn('standalone_invoices', 'recipient')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildStandaloneInvoicesTableSqlite();

            return;
        }

        $this->fixStandaloneInvoicesTableMysql();
    }

    public function down(): void
    {
        // No-op: prior broken state should not be restored.
    }

    private function fixStandaloneInvoicesTableMysql(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->dropForeignKeyIfExists('standalone_invoices', 'standalone_invoices_recipient_addrbook_id_foreign');
        $this->dropForeignKeyIfExists('standalone_invoices', 'standalone_invoices_sender_addrbook_id_foreign');
        $this->dropForeignKeyIfExists('standalone_invoices', 'standalone_invoices_user_id_foreign');

        if (Schema::hasColumn('standalone_invoices', 'recipient_addrbook_id')) {
            Schema::table('standalone_invoices', function (Blueprint $table) {
                $table->dropColumn('recipient_addrbook_id');
            });
        }

        if (Schema::hasColumn('standalone_invoices', 'recipient_name')) {
            DB::statement('ALTER TABLE standalone_invoices CHANGE recipient_name recipient TEXT NOT NULL');
        }

        Schema::enableForeignKeyConstraints();
    }

    private function rebuildStandaloneInvoicesTableSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('standalone_invoices_new', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->date('date');
            $table->text('recipient');
            $table->unsignedInteger('sender_addrbook_id')->nullable()->index();
            $table->string('template', 32)->default('classic');
            $table->text('terms_of_payment')->nullable();
            $table->text('pay_to')->nullable();
            $table->string('signatory_name')->nullable();
            $table->decimal('total_qty', 16, 4)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->timestamps();

            $table->index('date');
            $table->index('number');
        });

        $rows = DB::table('standalone_invoices')->get();
        foreach ($rows as $row) {
            DB::table('standalone_invoices_new')->insert([
                'id' => $row->id,
                'number' => $row->number,
                'date' => $row->date,
                'recipient' => $row->recipient_name ?? '',
                'sender_addrbook_id' => $row->sender_addrbook_id,
                'template' => $row->template,
                'terms_of_payment' => $row->terms_of_payment,
                'pay_to' => $row->pay_to,
                'signatory_name' => $row->signatory_name,
                'total_qty' => $row->total_qty,
                'subtotal' => $row->subtotal,
                'notes' => $row->notes,
                'user_id' => $row->user_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('standalone_invoices');
        Schema::rename('standalone_invoices_new', 'standalone_invoices');

        Schema::enableForeignKeyConstraints();
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
