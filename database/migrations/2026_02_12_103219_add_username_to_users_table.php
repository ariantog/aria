<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->after('name')->nullable();
        });

        // Backfill existing users
        $users = \App\Models\User::all();
        foreach ($users as $user) {
            // Create a username from email (e.g., john.doe@example.com -> john.doe)
            $username = explode('@', $user->email)[0];

            // Ensure uniqueness (basic check, append id if needed)
            if (\App\Models\User::where('username', $username)->exists()) {
                $username = $username.$user->id;
            }

            $user->update(['username' => $username]);
        }

        // Now make it unique and not nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
