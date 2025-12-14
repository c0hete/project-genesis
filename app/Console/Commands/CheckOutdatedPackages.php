<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\HubEventReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

/**
 * Check Outdated Packages Command
 *
 * Checks for outdated Composer packages and reports to Hub if any found.
 * Helps maintain security and stay up-to-date.
 *
 * Runs weekly via cron.
 *
 * Schedule in App\Console\Kernel:
 * $schedule->command('packages:check-outdated')->weekly();
 */
class CheckOutdatedPackages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:check-outdated
                            {--report : Report to Hub even if no updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for outdated Composer packages and report to Hub';

    /**
     * Execute the console command.
     */
    public function handle(HubEventReporter $reporter): int
    {
        $this->info('Checking for outdated packages...');

        try {
            // Run composer outdated in JSON format
            $result = Process::run('composer outdated --direct --format=json');

            if (!$result->successful() && $result->exitCode() !== 0) {
                $this->error('Failed to run composer outdated command');
                Log::error('[CheckOutdatedPackages] Composer command failed', [
                    'exit_code' => $result->exitCode(),
                    'output' => $result->output(),
                ]);
                return self::FAILURE;
            }

            $data = json_decode($result->output(), true);
            $outdated = $data['installed'] ?? [];

            if (empty($outdated)) {
                $this->info('All packages are up to date!');

                if ($this->option('report')) {
                    $reporter->send('MetricReported', [
                        'metric' => 'outdated_packages',
                        'value' => 0,
                        'unit' => 'count',
                        'period' => '7d',
                    ]);
                }

                return self::SUCCESS;
            }

            $this->warn("Found " . count($outdated) . " outdated package(s):");

            $securityCount = 0;

            foreach ($outdated as $package) {
                $name = $package['name'];
                $current = $package['version'];
                $latest = $package['latest'] ?? $package['latest-status'];

                $warning = isset($package['warning']) ? ' [SECURITY]' : '';
                if ($warning) {
                    $securityCount++;
                }

                $this->line("  - {$name}: {$current} -> {$latest}{$warning}");
            }

            // Report to Hub
            if (config('services.monitoring.report_outdated_packages', true)) {
                // Report count
                $reporter->reportMetric(
                    metric: 'outdated_packages',
                    value: count($outdated),
                    unit: 'count',
                    period: '7d'
                );

                // Report security issues as errors if any
                if ($securityCount > 0) {
                    $reporter->reportError(
                        severity: 'high',
                        message: "Found {$securityCount} package(s) with security vulnerabilities",
                        context: [
                            'outdated_count' => count($outdated),
                            'security_count' => $securityCount,
                            'packages' => array_map(fn($p) => [
                                'name' => $p['name'],
                                'current' => $p['version'],
                                'latest' => $p['latest'] ?? $p['latest-status'],
                                'has_warning' => isset($p['warning']),
                            ], $outdated),
                        ]
                    );

                    $this->error("\nSecurity vulnerabilities detected! Please update packages.");
                }

                $this->info("\nReported to Hub Personal.");
            }

            Log::info('[CheckOutdatedPackages] Check complete', [
                'outdated_count' => count($outdated),
                'security_count' => $securityCount,
            ]);

            return $securityCount > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error checking packages: {$e->getMessage()}");

            Log::error('[CheckOutdatedPackages] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
