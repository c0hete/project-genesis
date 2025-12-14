<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\BookingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Send Booking Reminders Command
 *
 * Sends reminder notifications to clients 24 hours before their booking.
 * Runs hourly via cron.
 *
 * Schedule in App\Console\Kernel:
 * $schedule->command('bookings:send-reminders')->hourly();
 */
class SendReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-reminders
                            {--dry-run : Run without sending notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for bookings scheduled in the next 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Finding bookings requiring reminders...');

        // Get bookings that need reminders
        $bookings = Booking::with(['service', 'user'])
            ->requireingReminder()
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings require reminders at this time.');
            return self::SUCCESS;
        }

        $this->info("Found {$bookings->count()} booking(s) requiring reminders.");

        $sent = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                if ($isDryRun) {
                    $this->line("Would send reminder for booking {$booking->id} to {$booking->client_email}");
                } else {
                    // Send notification
                    if ($booking->user) {
                        $booking->user->notify(new BookingReminderNotification($booking));
                    } else {
                        // Send to email if no user account
                        \Notification::route('mail', $booking->client_email)
                            ->notify(new BookingReminderNotification($booking));
                    }

                    // Mark as reminded
                    $booking->markReminded();

                    $this->line("Sent reminder for booking {$booking->id}");
                    $sent++;
                }

                Log::info('[SendReminders] Reminder sent', [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->client_email,
                    'scheduled_at' => $booking->scheduled_at->toIso8601String(),
                    'dry_run' => $isDryRun,
                ]);

            } catch (\Exception $e) {
                $this->error("Failed to send reminder for booking {$booking->id}: {$e->getMessage()}");
                $failed++;

                Log::error('[SendReminders] Failed to send reminder', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($isDryRun) {
            $this->info("Dry run complete. Would have sent {$bookings->count()} reminders.");
        } else {
            $this->info("Reminder sending complete. Sent: {$sent}, Failed: {$failed}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
