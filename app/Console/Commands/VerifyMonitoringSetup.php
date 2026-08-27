<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Enforces that the Xquisite monitoring integration stays intact — a
 * heartbeat job, a health endpoint, and the JS error beacon wired into at
 * least one layout. Run via monitoring:verify, wired into .git/hooks/
 * pre-commit and pre-push so this can't get silently deleted or forgotten
 * on a future change. Registering the instance + token on Xquisite's own
 * dashboard is a separate manual step — this only checks the code is here.
 */
class VerifyMonitoringSetup extends Command
{
    protected $signature   = 'monitoring:verify';
    protected $description = 'Check the Xquisite monitoring integration (heartbeat job, health route, JS beacon) is still present';

    public function handle(): int
    {
        $problems = [];

        if (!file_exists(app_path('Jobs/ReportHealthStatus.php'))) {
            $problems[] = 'app/Jobs/ReportHealthStatus.php is missing — the 5-minute heartbeat job.';
        }

        $beaconPath = resource_path('views/partials/monitoring-beacon.blade.php');
        if (!file_exists($beaconPath)) {
            $problems[] = 'resources/views/partials/monitoring-beacon.blade.php is missing — the JS error beacon.';
        } else {
            $includedSomewhere = false;
            foreach ($this->allBladeFiles() as $view) {
                if (str_contains(file_get_contents($view), "partials.monitoring-beacon")) {
                    $includedSomewhere = true;
                    break;
                }
            }
            if (!$includedSomewhere) {
                $problems[] = "monitoring-beacon.blade.php exists but isn't @include'd in any view — it'll never actually run.";
            }
        }

        if (!str_contains(file_get_contents(config_path('services.php')), "'monitoring'")) {
            $problems[] = "config/services.php is missing the 'monitoring' block (enabled/url/token).";
        }

        $scheduledSomewhere = false;
        $kernelPath = app_path('Console/Kernel.php');
        $consolePath = base_path('routes/console.php');
        foreach ([$kernelPath, $consolePath] as $path) {
            if (file_exists($path) && str_contains(file_get_contents($path), 'ReportHealthStatus')) {
                $scheduledSomewhere = true;
            }
        }
        if (!$scheduledSomewhere) {
            $problems[] = 'ReportHealthStatus is never scheduled — check app/Console/Kernel.php or routes/console.php.';
        }

        $healthRouteFound = false;
        foreach (['routes/web.php', 'routes/api.php'] as $routeFile) {
            $path = base_path($routeFile);
            if (file_exists($path) && str_contains(file_get_contents($path), "'/api/health'")) {
                $healthRouteFound = true;
            }
        }
        if (!$healthRouteFound) {
            $problems[] = "No '/api/health' route found — Xquisite's pull-based check has nothing to poll.";
        }

        if (empty($problems)) {
            $this->info('✓ Xquisite monitoring integration is intact.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Xquisite monitoring integration is broken:');
        foreach ($problems as $p) {
            $this->line("  - {$p}");
        }
        $this->newLine();
        $this->warn('If this was deliberate, update this check (app/Console/Commands/VerifyMonitoringSetup.php)');
        $this->warn('and let Xoliswa know the instance may need re-registering on the Xquisite dashboard.');

        return self::FAILURE;
    }

    /** @return array<string> */
    private function allBladeFiles(): array
    {
        $dir = resource_path('views');
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
