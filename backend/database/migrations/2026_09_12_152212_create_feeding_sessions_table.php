<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeding_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->string('type'); // breast, bottle
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('side')->nullable(); // left, right, both (breast only)
            $table->unsignedInteger('volume_ml')->nullable(); // bottle only
            $table->string('contents')->nullable(); // formula, expressed_milk (bottle only)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['baby_id', 'client_uuid']);
            $table->index(['baby_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeding_sessions');
    }
};
