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
        $primary = $this->seedUser(
            email: env('SEED_USER_EMAIL', 'hpbandara94@gmail.com'),
            password: env('SEED_USER_PASSWORD', 'password'),
            name: 'Parent',
        );

        $baby = Baby::firstOrCreate(
            ['user_id' => $primary->id],
            [
                'name' => env('SEED_BABY_NAME', 'Baby'),
                'birth_date' => env('SEED_BABY_BIRTH_DATE', now()->toDateString()),
                'sex' => 'unspecified',
            ]
        );
        $baby->users()->syncWithoutDetaching([$primary->id]);

        // Second caregiver for the SAME baby (e.g. the other parent) — not a
        // separate account/baby, so both can log for the one real baby.
        $coParent = $this->seedUser(
            email: env('SEED_CO_PARENT_EMAIL', 'vishmanthi@gmail.com'),
            password: env('SEED_CO_PARENT_PASSWORD', 'password'),
            name: 'Co-parent',
        );
        $baby->users()->syncWithoutDetaching([$coParent->id]);

        // A separate account + baby for testing, so poking around never
        // touches the real account's data above.
        $testUser = $this->seedUser(
            email: env('SEED_TEST_USER_EMAIL', 'test@example.com'),
            password: env('SEED_TEST_USER_PASSWORD', 'password'),
            name: 'Test User',
        );

        $testBaby = Baby::firstOrCreate(
            ['user_id' => $testUser->id],
            [
                'name' => 'Test Baby',
                'birth_date' => now()->subWeeks(6)->toDateString(),
                'sex' => 'unspecified',
            ]
        );
        $testBaby->users()->syncWithoutDetaching([$testUser->id]);

        $thirdUser = $this->seedUser(
            email: env('SEED_THIRD_USER_EMAIL', 'poorni@gmail.com'),
            password: env('SEED_THIRD_USER_PASSWORD', '123456'),
            name: 'Poorni Uththara',
        );

        //poo
        $thirdBaby = Baby::firstOrCreate(
            ['user_id' => $thirdUser->id],
            [
                'name' => env('SEED_BABY_NAME', 'Baby'),
                'birth_date' => env('SEED_BABY_BIRTH_DATE', now()->toDateString()),
                'sex' => 'unspecified',
            ]
        );
        $thirdBaby->users()->syncWithoutDetaching([$thirdUser->id]);
    }

    private function seedUser(string $email, string $password, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
            ]
        );

        $this->command?->info("Seeded login: {$email} / {$password}");

        return $user;
    }
}
