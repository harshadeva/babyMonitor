<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->timestampTz('given_at');
            $table->string('name'); // e.g. Vitamin D
            $table->string('dose')->nullable(); // e.g. "400 IU", "1 ml"
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['baby_id', 'client_uuid']);
            $table->index(['baby_id', 'given_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_doses');
    }
};
