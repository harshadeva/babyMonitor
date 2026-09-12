<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptom_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->timestampTz('occurred_at');
            $table->string('tag'); // fussy, spit_up, rash, jaundice_look, congestion, other
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['baby_id', 'client_uuid']);
            $table->index(['baby_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptom_logs');
    }
};
