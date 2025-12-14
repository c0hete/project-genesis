<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Seeding database...');

        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Seed services
        $this->call(ServiceSeeder::class);

        // Seed bookings (creates clients and staff automatically)
        $this->call(BookingSeeder::class);

        $this->command->info('Database seeding complete!');
    }
}
