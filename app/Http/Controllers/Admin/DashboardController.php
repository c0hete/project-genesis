<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\MetricsCollector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Dashboard Controller
 *
 * Inspired by Stripe Dashboard, Calendly Analytics, and Salesforce Reports
 *
 * Features:
 * - Real-time business metrics
 * - Revenue analytics
 * - Booking trends
 * - Upcoming appointments
 * - Quick actions
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, MetricsCollector $metrics): Response
    {
        // Ensure user is admin
        $this->authorize('viewAnalytics', Booking::class);

        $todayMetrics = $metrics->getTodayMetrics();
        $currentMetrics = $metrics->getCurrentMetrics();

        return Inertia::render('Admin/Dashboard', [
            // Key metrics (Stripe pattern - 4 main cards)
            'keyMetrics' => [
                [
                    'label' => 'Revenue Today',
                    'value' => '$' . number_format($todayMetrics['revenue_cents'] / 100, 2),
                    'change' => '+12%', // TODO: Calculate actual change
                    'trend' => 'up',
                    'icon' => 'currency-dollar',
                ],
                [
                    'label' => 'Bookings Today',
                    'value' => $todayMetrics['bookings_created'],
                    'subtitle' => $todayMetrics['bookings_confirmed'] . ' confirmed',
                    'icon' => 'calendar',
                ],
                [
                    'label' => 'Upcoming (24h)',
                    'value' => $currentMetrics['upcoming_bookings_24h'],
                    'subtitle' => 'Next day appointments',
                    'icon' => 'clock',
                ],
                [
                    'label' => 'Completion Rate',
                    'value' => $this->getCompletionRate($todayMetrics) . '%',
                    'subtitle' => $todayMetrics['no_shows'] . ' no-shows',
                    'trend' => 'neutral',
                    'icon' => 'check-circle',
                ],
            ],

            // Booking trends (Calendly pattern - 7 day chart)
            'bookingTrends' => $metrics->getBookingTrends(7),

            // Popular services (Stripe pattern)
            'popularServices' => $metrics->getPopularServices(5),

            // Revenue breakdown
            'revenueByService' => $metrics->getRevenueByService(),

            // Upcoming appointments (next 10)
            'upcomingBookings' => Booking::query()
                ->upcoming()
                ->with(['service', 'user', 'assignedStaff'])
                ->orderBy('scheduled_at')
                ->limit(10)
                ->get()
                ->map(fn($booking) => [
                    'id' => $booking->id,
                    'client_name' => $booking->client_name,
                    'service_name' => $booking->service->name,
                    'scheduled_at' => $booking->scheduled_at->format('M d, Y H:i'),
                    'status' => $booking->status_label,
                    'status_color' => $booking->status_color,
                    'amount' => '$' . $booking->formatted_amount,
                    'assigned_to' => $booking->assignedStaff?->name ?? 'Unassigned',
                ]),

            // Recent activity (Salesforce pattern)
            'recentActivity' => Booking::query()
                ->with(['service', 'user'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn($booking) => [
                    'type' => 'booking.' . $booking->status->value,
                    'message' => $this->getActivityMessage($booking),
                    'timestamp' => $booking->updated_at->diffForHumans(),
                    'status_color' => $booking->status_color,
                ]),

            // Business insights
            'insights' => [
                'no_show_rate' => $metrics->getNoShowRate(),
                'cancellation_rate' => $metrics->getCancellationRate(),
                'active_sessions' => $currentMetrics['active_sessions'],
                'pending_payments' => $currentMetrics['pending_payments'],
            ],

            // Quick stats for today
            'todayStats' => [
                'created' => $todayMetrics['bookings_created'],
                'confirmed' => $todayMetrics['bookings_confirmed'],
                'completed' => $todayMetrics['bookings_completed'],
                'cancelled' => $todayMetrics['bookings_cancelled'],
                'no_shows' => $todayMetrics['no_shows'],
            ],
        ]);
    }

    private function getCompletionRate(array $todayMetrics): int
    {
        $total = $todayMetrics['bookings_completed'] + $todayMetrics['no_shows'];

        if ($total === 0) {
            return 100;
        }

        return (int) (($todayMetrics['bookings_completed'] / $total) * 100);
    }

    private function getActivityMessage(Booking $booking): string
    {
        return match ($booking->status->value) {
            'created' => "{$booking->client_name} created booking for {$booking->service->name}",
            'confirmed' => "{$booking->client_name} confirmed {$booking->service->name}",
            'completed' => "Completed: {$booking->service->name} with {$booking->client_name}",
            'cancelled' => "{$booking->client_name} cancelled {$booking->service->name}",
            'no_show' => "No-show: {$booking->client_name} for {$booking->service->name}",
            default => "{$booking->client_name} - {$booking->service->name}",
        };
    }
}
