<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Mark No-Shows Command
 *
 * Automatically marks bookings as no-show if:
 * - Scheduled time has passed by more than 15 minutes
 * - Status is still confirmed or reminded
 * - Not started
 *
 * Runs hourly via cron.
 *
 * Schedule in App\Console\Kernel:
 * $schedule->command('bookings:mark-no-shows')->hourly();
 */
class MarkNoShows extends Command
{
    /**
     * Grace period in minutes after scheduled time
     */
    private const GRACE_PERIOD_MINUTES = 15;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:mark-no-shows
                            {--grace-period=15 : Minutes after scheduled time to wait}
                            {--dry-run : Run without marking bookings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark bookings as no-show if client did not arrive';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $gracePeriod = (int) $this->option('grace-period');

        $this->info("Checking for no-shows (grace period: {$gracePeriod} minutes)...");

        // Find bookings that should be marked as no-show
        $cutoffTime = now()->subMinutes($gracePeriod);

        $bookings = Booking::with(['service', 'user'])
            ->whereIn('status', [
                BookingStatus::CONFIRMED->value,
                BookingStatus::REMINDED->value,
            ])
            ->where('scheduled_at', '<=', $cutoffTime)
            ->whereNull('started_at')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings to mark as no-show.');
            return self::SUCCESS;
        }

        $this->info("Found {$bookings->count()} booking(s) to mark as no-show.");

        $marked = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                if ($isDryRun) {
                    $this->line("Would mark booking {$booking->id} as no-show (scheduled: {$booking->scheduled_at})");
                } else {
                    $booking->markNoShow();

                    $this->line("Marked booking {$booking->id} as no-show");
                    $marked++;

                    // Optional: Send notification to business owner
                    // You could notify admin here if needed
                }

                Log::info('[MarkNoShows] Booking marked as no-show', [
                    'booking_id' => $booking->id,
                    'client_email' => $booking->client_email,
                    'scheduled_at' => $booking->scheduled_at->toIso8601String(),
                    'service' => $booking->service->name,
                    'dry_run' => $isDryRun,
                ]);

            } catch (\Exception $e) {
                $this->error("Failed to mark booking {$booking->id} as no-show: {$e->getMessage()}");
                $failed++;

                Log::error('[MarkNoShows] Failed to mark no-show', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($isDryRun) {
            $this->info("Dry run complete. Would have marked {$bookings->count()} bookings as no-show.");
        } else {
            $this->info("No-show marking complete. Marked: {$marked}, Failed: {$failed}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
