<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diaper_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->timestampTz('occurred_at');
            $table->string('product'); // disposable, cloth
            $table->boolean('wet')->default(false);
            $table->boolean('dirty')->default(false);
            $table->string('stool_color_name')->nullable();
            $table->string('stool_color_hex')->nullable();
            $table->string('stool_consistency')->nullable(); // seedy, pasty, watery, hard, mucousy
            $table->boolean('flagged_for_doctor')->default(false); // red or pale/clay stool
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['baby_id', 'client_uuid']);
            $table->index(['baby_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diaper_changes');
    }
};
