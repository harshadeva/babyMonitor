<?php

namespace Database\Seeders;

use App\Models\Baby;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = env('SEED_USER_EMAIL', 'hpbandara94@gmail.com');
        $password = env('SEED_USER_PASSWORD', 'password');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Parent',
                'password' => $password,
            ]
        );

        Baby::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => env('SEED_BABY_NAME', 'Baby'),
                'birth_date' => env('SEED_BABY_BIRTH_DATE', now()->toDateString()),
                'sex' => 'unspecified',
            ]
        );

        $this->command?->info("Seeded login: {$email} / {$password}");
    }
}
