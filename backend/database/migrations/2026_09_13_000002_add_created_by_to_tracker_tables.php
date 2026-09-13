<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'feeding_sessions',
        'sleep_sessions',
        'diaper_changes',
        'temperature_readings',
        'growth_measurements',
        'medication_doses',
        'symptom_logs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('baby_id')->constrained('users')->nullOnDelete();
            });

            // Existing rows predate attribution — credit them to the baby's original owner.
            DB::statement("update {$table} set created_by = babies.user_id from babies where babies.id = {$table}.baby_id");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }
};
