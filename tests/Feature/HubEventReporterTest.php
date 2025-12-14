<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\HubEventReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Hub Event Reporter Feature Tests
 *
 * Tests event reporting to Hub Personal API.
 * Uses mocked HTTP requests to avoid hitting real API.
 */
class HubEventReporterTest extends TestCase
{
    use RefreshDatabase;

    private HubEventReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure Hub for testing
        Config::set('services.hub.events_enabled', true);
        Config::set('services.hub.event_source', 'genesis-test');
        Config::set('services.hub.api_url', 'https://hub.test/api/v1/hub');
        Config::set('services.hub.api_token', 'test-token');

        $this->reporter = new HubEventReporter();
    }

    public function test_reporter_checks_configuration(): void
    {
        $this->assertTrue($this->reporter->isConfigured());
    }

    public function test_reporter_detects_missing_configuration(): void
    {
        Config::set('services.hub.api_token', '');

        $reporter = new HubEventReporter();

        $this->assertFalse($reporter->isConfigured());
        $this->assertContains('services.hub.api_token', $reporter->getMissingConfig());
    }

    public function test_send_event_when_disabled(): void
    {
        Config::set('services.hub.events_enabled', false);

        $reporter = new HubEventReporter();

        // Should succeed without making HTTP request
        $result = $reporter->send('AgentHeartbeat', ['status' => 'healthy']);

        $this->assertTrue($result);
    }

    public function test_send_agent_heartbeat(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->reporter->heartbeat([
            'today' => [
                'bookings_created' => 5,
                'revenue_cents' => 10000,
            ],
        ]);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hub.test/api/v1/hub/events'
                && $request['type'] === 'AgentHeartbeat'
                && $request['source'] === 'genesis-test'
                && isset($request['payload']['status'])
                && isset($request['payload']['uptime_seconds']);
        });
    }

    public function test_send_booking_event(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->reporter->bookingEvent('booking.created', [
            'booking_id' => 'test-123',
            'service_name' => 'Test Service',
            'amount_cents' => 5000,
        ]);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request['type'] === 'InteractionDetected'
                && $request['payload']['action'] === 'booking.created'
                && $request['payload']['booking_id'] === 'test-123';
        });
    }

    public function test_send_payment_event(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->reporter->paymentEvent('payment.succeeded', [
            'payment_id' => 'pay-123',
            'gateway' => 'stripe',
            'amount' => 5000,
        ]);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request['type'] === 'InteractionDetected'
                && $request['payload']['action'] === 'payment.succeeded'
                && $request['payload']['gateway'] === 'stripe';
        });
    }

    public function test_report_error(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->reporter->reportError(
            severity: 'high',
            message: 'Database connection failed',
            context: ['host' => 'localhost']
        );

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request['type'] === 'ErrorReported'
                && $request['payload']['severity'] === 'high'
                && $request['payload']['message'] === 'Database connection failed';
        });
    }

    public function test_report_metric(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->reporter->reportMetric(
            metric: 'daily_bookings',
            value: 42,
            unit: 'count',
            period: '24h'
        );

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request['type'] === 'MetricReported'
                && $request['payload']['metric'] === 'daily_bookings'
                && $request['payload']['value'] === 42;
        });
    }

    public function test_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $result = $this->reporter->send('AgentHeartbeat', ['status' => 'healthy']);

        // Should not throw exception, just return false
        $this->assertFalse($result);
    }

    public function test_handles_network_timeout_gracefully(): void
    {
        Http::fake(function () {
            throw new \Exception('Network timeout');
        });

        $result = $this->reporter->send('AgentHeartbeat', ['status' => 'healthy']);

        // Should not throw exception, just return false
        $this->assertFalse($result);
    }

    public function test_event_structure_is_valid(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['success' => true], 200),
        ]);

        $this->reporter->send('AgentHeartbeat', ['status' => 'healthy']);

        Http::assertSent(function ($request) {
            // Check all required fields are present
            return isset($request['id'])
                && isset($request['type'])
                && isset($request['version'])
                && isset($request['source'])
                && isset($request['occurred_at'])
                && isset($request['payload'])
                && $request['version'] === 1
                && strlen($request['id']) === 26; // ULID length
        });
    }

    public function test_occurred_at_is_iso8601_format(): void
    {
        Http::fake([
            'hub.test/*' => Http::response(['success' => true], 200),
        ]);

        $this->reporter->send('AgentHeartbeat', ['status' => 'healthy']);

        Http::assertSent(function ($request) {
            $occurredAt = $request['occurred_at'];

            // Verify ISO8601 format
            return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $occurredAt) === 1;
        });
    }
}
