<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperature_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->timestampTz('measured_at');
            $table->decimal('value_celsius', 4, 1);
            $table->string('method'); // rectal, oral, armpit, forehead, ear
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['baby_id', 'client_uuid']);
            $table->index(['baby_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_readings');
    }
};
