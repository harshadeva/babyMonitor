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
        $this->seedUserWithBaby(
            email: env('SEED_USER_EMAIL', 'hpbandara94@gmail.com'),
            password: env('SEED_USER_PASSWORD', 'password'),
            name: 'Parent',
            babyName: env('SEED_BABY_NAME', 'Baby'),
            babyBirthDate: env('SEED_BABY_BIRTH_DATE', now()->toDateString()),
        );

        // A separate account + baby for testing, so poking around never
        // touches the real account's data above.
        $this->seedUserWithBaby(
            email: env('SEED_TEST_USER_EMAIL', 'test@example.com'),
            password: env('SEED_TEST_USER_PASSWORD', 'password'),
            name: 'Test User',
            babyName: 'Test Baby',
            babyBirthDate: now()->subWeeks(6)->toDateString(),
        );
    }

    private function seedUserWithBaby(
        string $email,
        string $password,
        string $name,
        string $babyName,
        string $babyBirthDate,
    ): void {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
            ]
        );

        Baby::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $babyName,
                'birth_date' => $babyBirthDate,
                'sex' => 'unspecified',
            ]
        );

        $this->command?->info("Seeded login: {$email} / {$password}");
    }
}
