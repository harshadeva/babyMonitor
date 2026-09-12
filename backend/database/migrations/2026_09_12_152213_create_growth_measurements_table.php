<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->timestampTz('measured_at');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('length_cm', 5, 1)->nullable();
            $table->decimal('head_circumference_cm', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['baby_id', 'client_uuid']);
            $table->index(['baby_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_measurements');
    }
};
