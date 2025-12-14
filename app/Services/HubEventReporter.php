<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Hub Event Reporter
 *
 * Reports events to Hub Personal API for Supervisor consumption.
 * Follows event model defined in Knowledge Base.
 *
 * @see C:\Users\JoseA\Projects\knowledge-base\supervisor\03_EVENT_MODEL.md
 */
class HubEventReporter
{
    private bool $enabled;
    private string $apiUrl;
    private string $apiToken;
    private string $source;

    public function __construct()
    {
        $this->enabled = config('services.hub.events_enabled', false);
        $this->apiUrl = config('services.hub.api_url', '');
        $this->apiToken = config('services.hub.api_token', '');
        $this->source = config('services.hub.event_source', '');
    }

    /**
     * Send event to Hub Personal
     *
     * @param string $type Event type (AppRegistered, AgentHeartbeat, etc.)
     * @param array $payload Event-specific data
     * @param string|null $occurredAt ISO8601 timestamp (defaults to now)
     * @return bool Success status
     */
    public function send(string $type, array $payload, ?string $occurredAt = null): bool
    {
        // Skip if disabled (dev environment)
        if (!$this->enabled) {
            Log::debug('[HubEventReporter] Skipped (disabled)', [
                'type' => $type,
                'payload' => $payload,
            ]);
            return true;
        }

        // Validate configuration
        if (!$this->isConfigured()) {
            Log::error('[HubEventReporter] Not configured', [
                'missing' => $this->getMissingConfig(),
            ]);
            return false;
        }

        // Build event
        $event = $this->buildEvent($type, $payload, $occurredAt);

        try {
            // Send to Hub
            $response = Http::withToken($this->apiToken)
                ->timeout(5)
                ->post("{$this->apiUrl}/events", $event);

            if ($response->successful()) {
                Log::info('[HubEventReporter] Event sent', [
                    'type' => $type,
                    'id' => $event['id'],
                ]);
                return true;
            }

            // Log failure
            Log::error('[HubEventReporter] Failed to send event', [
                'type' => $type,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            // Never crash the app if Hub is down
            Log::error('[HubEventReporter] Exception', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send AgentHeartbeat event
     *
     * @param array $metrics Health metrics
     * @return bool
     */
    public function heartbeat(array $metrics): bool
    {
        return $this->send('AgentHeartbeat', array_merge([
            'status' => 'healthy',
            'uptime_seconds' => $this->getUptimeSeconds(),
        ], $metrics));
    }

    /**
     * Report booking interaction
     *
     * @param string $action booking.created, booking.confirmed, etc.
     * @param array $data Booking data
     * @return bool
     */
    public function bookingEvent(string $action, array $data): bool
    {
        return $this->send('InteractionDetected', array_merge([
            'action' => $action,
        ], $data));
    }

    /**
     * Report payment interaction
     *
     * @param string $action payment.succeeded, payment.failed, etc.
     * @param array $data Payment data
     * @return bool
     */
    public function paymentEvent(string $action, array $data): bool
    {
        return $this->send('InteractionDetected', array_merge([
            'action' => $action,
        ], $data));
    }

    /**
     * Report error
     *
     * @param string $severity critical, high, medium, low
     * @param string $message Error message
     * @param array $context Additional context
     * @return bool
     */
    public function reportError(string $severity, string $message, array $context = []): bool
    {
        return $this->send('ErrorReported', [
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'trace' => $context['trace'] ?? null,
        ]);
    }

    /**
     * Report metric
     *
     * @param string $metric Metric name
     * @param int|float $value Value
     * @param string $unit count, seconds, bytes, percentage
     * @param string $period 1h, 24h, 7d
     * @return bool
     */
    public function reportMetric(string $metric, int|float $value, string $unit, string $period): bool
    {
        return $this->send('MetricReported', [
            'metric' => $metric,
            'value' => $value,
            'unit' => $unit,
            'period' => $period,
        ]);
    }

    /**
     * Check if reporter is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl)
            && !empty($this->apiToken)
            && !empty($this->source);
    }

    /**
     * Get missing configuration keys
     *
     * @return array
     */
    public function getMissingConfig(): array
    {
        $missing = [];

        if (empty($this->apiUrl)) {
            $missing[] = 'services.hub.api_url';
        }

        if (empty($this->apiToken)) {
            $missing[] = 'services.hub.api_token';
        }

        if (empty($this->source)) {
            $missing[] = 'services.hub.event_source';
        }

        return $missing;
    }

    /**
     * Build event structure
     *
     * @param string $type
     * @param array $payload
     * @param string|null $occurredAt
     * @return array
     */
    private function buildEvent(string $type, array $payload, ?string $occurredAt): array
    {
        return [
            'id' => $this->generateUlid(),
            'type' => $type,
            'version' => 1,
            'source' => $this->source,
            'occurred_at' => $occurredAt ?? now()->toIso8601String(),
            'payload' => $payload,
        ];
    }

    /**
     * Generate ULID
     *
     * @return string
     */
    private function generateUlid(): string
    {
        return strtoupper((string) Str::ulid());
    }

    /**
     * Get application uptime in seconds
     *
     * @return int
     */
    private function getUptimeSeconds(): int
    {
        // Uptime since last deploy/restart
        // For Laravel, we can use cache to track last restart
        $lastRestart = cache()->remember('app.last_restart', 86400, fn() => now());

        return now()->diffInSeconds($lastRestart);
    }
}
