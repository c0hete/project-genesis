<?php

namespace App\Console\Commands;

use App\Services\HubEventReporter;
use App\Services\MetricsCollector;
use Illuminate\Console\Command;

/**
 * Send Heartbeat to Hub Personal
 *
 * Runs every 5 minutes via cron.
 * Reports health status and business metrics to Hub for Supervisor consumption.
 */
class SendHeartbeat extends Command
{
    protected $signature = 'hub:heartbeat';

    protected $description = 'Send heartbeat event to Hub Personal with current metrics';

    public function handle(HubEventReporter $reporter, MetricsCollector $metrics): int
    {
        if (!$reporter->isConfigured()) {
            $this->warn('Hub integration not configured. Skipping heartbeat.');
            $this->line('Missing: ' . implode(', ', $reporter->getMissingConfig()));
            return self::SUCCESS;
        }

        $this->info('Collecting metrics...');

        $payload = [
            'status' => 'healthy',
            'uptime_seconds' => $this->getUptimeSeconds(),

            'today' => $metrics->getTodayMetrics(),
            'current' => $metrics->getCurrentMetrics(),
            'health' => $metrics->getHealthMetrics(),
            'app' => $metrics->getAppInfo(),
            'server' => $metrics->getServerInfo(),
            'dependencies' => $metrics->getDependencyInfo(),
        ];

        $this->info('Sending heartbeat to Hub...');

        $success = $reporter->heartbeat($payload);

        if ($success) {
            $this->info('[OK] Heartbeat sent successfully');
            return self::SUCCESS;
        }

        $this->error('[ERROR] Failed to send heartbeat (check logs)');
        return self::FAILURE;
    }

    private function getUptimeSeconds(): int
    {
        $lastRestart = cache()->remember('app.last_restart', 86400, fn() => now());
        return now()->diffInSeconds($lastRestart);
    }
}
