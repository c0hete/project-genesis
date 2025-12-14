<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Booking Seeder
 *
 * Seeds demo bookings in all 8 states for testing and development.
 * Creates realistic booking scenarios.
 */
class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::where('is_active', true)->get();

        if ($services->isEmpty()) {
            $this->command->warn('No active services found. Run ServiceSeeder first.');
            return;
        }

        // Create demo user (client)
        $client = User::factory()->create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
        ]);

        // Create staff user
        $staff = User::factory()->create([
            'name' => 'Personal Spa',
            'email' => 'staff@example.com',
        ]);

        $bookingsCreated = 0;

        // Create 2 pending bookings (created, not paid)
        for ($i = 0; $i < 2; $i++) {
            Booking::factory()
                ->for($services->random())
                ->for($client, 'user')
                ->create([
                    'status' => BookingStatus::CREATED,
                    'scheduled_at' => now()->addDays(rand(5, 15))->setHour(rand(9, 17))->setMinute([0, 30][rand(0, 1)]),
                ]);
            $bookingsCreated++;
        }

        // Create 3 confirmed bookings (paid, upcoming)
        for ($i = 0; $i < 3; $i++) {
            Booking::factory()
                ->for($services->random())
                ->for($client, 'user')
                ->confirmed()
                ->create([
                    'scheduled_at' => now()->addDays(rand(1, 7))->setHour(rand(9, 17))->setMinute([0, 30][rand(0, 1)]),
                    'assigned_to' => $staff->id,
                ]);
            $bookingsCreated++;
        }

        // Create 2 reminded bookings (tomorrow)
        for ($i = 0; $i < 2; $i++) {
            Booking::factory()
                ->for($services->random())
                ->for($client, 'user')
                ->reminded()
                ->create([
                    'scheduled_at' => now()->addDay()->setHour(rand(9, 17))->setMinute([0, 30][rand(0, 1)]),
                    'assigned_to' => $staff->id,
                ]);
            $bookingsCreated++;
        }

        // Create 1 started booking (in progress)
        Booking::factory()
            ->for($services->random())
            ->for($client, 'user')
            ->started()
            ->create([
                'scheduled_at' => now()->subMinutes(30),
                'assigned_to' => $staff->id,
            ]);
        $bookingsCreated++;

        // Create 5 completed bookings (past)
        for ($i = 0; $i < 5; $i++) {
            Booking::factory()
                ->for($services->random())
                ->for($client, 'user')
                ->completed()
                ->create([
                    'scheduled_at' => now()->subDays(rand(1, 30))->setHour(rand(9, 17))->setMinute([0, 30][rand(0, 1)]),
                    'assigned_to' => $staff->id,
                ]);
            $bookingsCreated++;
        }

        // Create 1 cancelled booking
        Booking::factory()
            ->for($services->random())
            ->for($client, 'user')
            ->cancelled()
            ->create([
                'scheduled_at' => now()->addDays(3)->setHour(14)->setMinute(0),
                'cancelled_by' => $client->id,
            ]);
        $bookingsCreated++;

        // Create 1 no-show booking
        Booking::factory()
            ->for($services->random())
            ->for($client, 'user')
            ->noShow()
            ->create([
                'scheduled_at' => now()->subDays(2)->setHour(10)->setMinute(0),
                'assigned_to' => $staff->id,
            ]);
        $bookingsCreated++;

        // Create 1 rescheduled booking (old booking)
        $originalBooking = Booking::factory()
            ->for($services->random())
            ->for($client, 'user')
            ->rescheduled()
            ->create([
                'scheduled_at' => now()->addDays(5)->setHour(15)->setMinute(0),
            ]);

        // Create the new booking it was rescheduled to
        Booking::factory()
            ->for($originalBooking->service)
            ->for($client, 'user')
            ->create([
                'status' => BookingStatus::CONFIRMED,
                'scheduled_at' => now()->addDays(7)->setHour(11)->setMinute(0),
                'rescheduled_from' => $originalBooking->id,
                'is_paid' => true,
                'payment_status' => 'paid',
                'assigned_to' => $staff->id,
            ]);
        $bookingsCreated += 2;

        // Create some additional random bookings for different users
        for ($i = 0; $i < 5; $i++) {
            $randomClient = User::factory()->create();

            Booking::factory()
                ->for($services->random())
                ->for($randomClient, 'user')
                ->state([
                    'status' => collect([
                        BookingStatus::CREATED,
                        BookingStatus::CONFIRMED,
                        BookingStatus::COMPLETED,
                    ])->random(),
                    'scheduled_at' => now()->addDays(rand(-10, 20))->setHour(rand(9, 17))->setMinute([0, 30][rand(0, 1)]),
                ])
                ->create();
            $bookingsCreated++;
        }

        $this->command->info("Created {$bookingsCreated} bookings in various states");
        $this->command->info('Bookings by status:');
        $this->command->table(
            ['Status', 'Count'],
            collect(BookingStatus::cases())->map(fn ($status) => [
                $status->label(),
                Booking::where('status', $status)->count(),
            ])->toArray()
        );
    }
}
