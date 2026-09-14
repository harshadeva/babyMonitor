<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_uuid');
            $table->timestampTz('occurred_at');
            $table->string('title'); // e.g. "First word", "Cord stump fell off" — free text, not an enum
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['baby_id', 'client_uuid']);
            $table->index(['baby_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
