<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baby_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['baby_id', 'user_id']);
        });

        // Every existing baby's original owner becomes its first caregiver.
        $now = now();
        $rows = DB::table('babies')->select('id', 'user_id')->get()->map(fn ($baby) => [
            'baby_id' => $baby->id,
            'user_id' => $baby->user_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($rows->isNotEmpty()) {
            DB::table('baby_user')->insert($rows->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('baby_user');
    }
};
