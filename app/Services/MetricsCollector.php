<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Metrics Collector
 *
 * Collects business metrics for:
 * - Admin dashboard (local view)
 * - Hub heartbeat (global Supervisor view)
 *
 * Inspired by Stripe Dashboard, Datadog, and Salesforce Analytics
 */
class MetricsCollector
{
    /**
     * Get comprehensive metrics for today
     *
     * @return array
     */
    public function getTodayMetrics(): array
    {
        return Cache::remember('metrics.today', 300, function () {
            $today = now()->startOfDay();

            return [
                'bookings_created' => $this->getBookingsCreatedToday(),
                'bookings_confirmed' => $this->getBookingsConfirmedToday(),
                'bookings_completed' => $this->getBookingsCompletedToday(),
                'bookings_cancelled' => $this->getBookingsCancelledToday(),
                'no_shows' => $this->getNoShowsToday(),
                'revenue_cents' => $this->getRevenueToday(),
                'currency' => config('app.currency', 'USD'),
            ];
        });
    }

    /**
     * Get current real-time metrics
     *
     * @return array
     */
    public function getCurrentMetrics(): array
    {
        return [
            'active_sessions' => $this->getActiveSessions(),
            'pending_payments' => $this->getPendingPayments(),
            'upcoming_bookings_24h' => $this->getUpcomingBookings24h(),
        ];
    }

    /**
     * Get system health metrics
     *
     * @return array
     */
    public function getHealthMetrics(): array
    {
        return [
            'db_latency_ms' => $this->getDatabaseLatency(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'queue_pending' => $this->getQueuePending(),
            'memory_usage_percent' => $this->getMemoryUsagePercent(),
        ];
    }

    /**
     * Get application info
     *
     * @return array
     */
    public function getAppInfo(): array
    {
        return [
            'version' => config('app.version', '1.0.0'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
        ];
    }

    /**
     * Get server info
     *
     * @return array
     */
    public function getServerInfo(): array
    {
        return [
            'id' => config('services.server.id'),
            'ip' => config('services.server.ip'),
            'hostname' => config('services.server.hostname'),
        ];
    }

    /**
     * Get dependency info (security check)
     *
     * @return array
     */
    public function getDependencyInfo(): array
    {
        return [
            'outdated_count' => $this->getOutdatedPackagesCount(),
            'security_vulnerabilities' => 0, // TODO: Integrate with composer audit
            'last_composer_update' => $this->getLastComposerUpdate(),
        ];
    }

    /**
     * Get popular services (for dashboard - Stripe pattern)
     *
     * @return array
     */
    public function getPopularServices(int $limit = 5): array
    {
        return Cache::remember("metrics.popular_services.{$limit}", 3600, function () use ($limit) {
            return Booking::query()
                ->select('service_id', DB::raw('COUNT(*) as bookings_count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('service_id')
                ->orderByDesc('bookings_count')
                ->limit($limit)
                ->with('service')
                ->get()
                ->map(function ($item) {
                    return [
                        'service_name' => $item->service->name,
                        'bookings_count' => $item->bookings_count,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get revenue breakdown by service
     *
     * @return array
     */
    public function getRevenueByService(): array
    {
        return Cache::remember('metrics.revenue_by_service', 3600, function () {
            return Booking::query()
                ->select('service_id', DB::raw('SUM(amount_cents) as total_revenue'))
                ->where('status', BookingStatus::COMPLETED->value)
                ->where('completed_at', '>=', now()->subDays(30))
                ->groupBy('service_id')
                ->with('service')
                ->get()
                ->map(function ($item) {
                    return [
                        'service_name' => $item->service->name,
                        'revenue_cents' => $item->total_revenue,
                        'revenue_formatted' => number_format($item->total_revenue / 100, 2),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get booking trends (Salesforce Analytics pattern)
     *
     * @param int $days
     * @return array
     */
    public function getBookingTrends(int $days = 7): array
    {
        $data = Booking::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->toArray(),
            'values' => $data->pluck('count')->toArray(),
        ];
    }

    /**
     * Get no-show rate (important business metric)
     *
     * @return float
     */
    public function getNoShowRate(): float
    {
        $total = Booking::query()
            ->whereIn('status', [
                BookingStatus::COMPLETED->value,
                BookingStatus::NO_SHOW->value,
            ])
            ->where('scheduled_at', '>=', now()->subDays(30))
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $noShows = Booking::query()
            ->where('status', BookingStatus::NO_SHOW->value)
            ->where('scheduled_at', '>=', now()->subDays(30))
            ->count();

        return round(($noShows / $total) * 100, 2);
    }

    /**
     * Get cancellation rate
     *
     * @return float
     */
    public function getCancellationRate(): float
    {
        $total = Booking::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $cancelled = Booking::query()
            ->where('status', BookingStatus::CANCELLED->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return round(($cancelled / $total) * 100, 2);
    }

    // Private helpers

    private function getBookingsCreatedToday(): int
    {
        return Booking::whereDate('created_at', today())->count();
    }

    private function getBookingsConfirmedToday(): int
    {
        return Booking::whereDate('created_at', today())
            ->where('status', BookingStatus::CONFIRMED->value)
            ->count();
    }

    private function getBookingsCompletedToday(): int
    {
        return Booking::whereDate('completed_at', today())
            ->where('status', BookingStatus::COMPLETED->value)
            ->count();
    }

    private function getBookingsCancelledToday(): int
    {
        return Booking::whereDate('cancelled_at', today())
            ->where('status', BookingStatus::CANCELLED->value)
            ->count();
    }

    private function getNoShowsToday(): int
    {
        return Booking::whereDate('scheduled_at', today())
            ->where('status', BookingStatus::NO_SHOW->value)
            ->count();
    }

    private function getRevenueToday(): int
    {
        return Booking::whereDate('completed_at', today())
            ->where('status', BookingStatus::COMPLETED->value)
            ->sum('amount_cents');
    }

    private function getActiveSessions(): int
    {
        return Booking::where('status', BookingStatus::STARTED->value)->count();
    }

    private function getPendingPayments(): int
    {
        return Booking::where('payment_status', 'pending')->count();
    }

    private function getUpcomingBookings24h(): int
    {
        return Booking::whereBetween('scheduled_at', [now(), now()->addHours(24)])
            ->whereIn('status', [
                BookingStatus::CONFIRMED->value,
                BookingStatus::REMINDED->value,
            ])
            ->count();
    }

    private function getDatabaseLatency(): int
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        $end = microtime(true);

        return (int) (($end - $start) * 1000);
    }

    private function getCacheHitRate(): float
    {
        // Simplified - real implementation would track cache hits/misses
        return 0.95;
    }

    private function getQueuePending(): int
    {
        return DB::table('jobs')->count();
    }

    private function getMemoryUsagePercent(): int
    {
        $used = memory_get_usage(true);
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return 0;
        }

        $limit = $this->convertToBytes($limit);

        return (int) (($used / $limit) * 100);
    }

    private function getOutdatedPackagesCount(): int
    {
        // TODO: Implement composer outdated check
        return 0;
    }

    private function getLastComposerUpdate(): string
    {
        $composerLock = base_path('composer.lock');

        if (!file_exists($composerLock)) {
            return 'never';
        }

        return date('Y-m-d', filemtime($composerLock));
    }

    private function convertToBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
