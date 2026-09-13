<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BenchmarkService;
use Illuminate\Console\Command;

final class BenchmarkRunCommand extends Command
{
    protected $signature = 'benchmark:run {scenario=demo_normal} {--seeds=1,2,3 : Comma-separated non-negative seeds (up to 100)}';

    protected $description = 'Run a reproducible Courier AI vs baseline benchmark.';

    public function handle(BenchmarkService $benchmarks): int
    {
        $seeds = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('seeds'))), static fn (string $seed): bool => $seed !== ''));
        foreach ($seeds as $seed) {
            if (! preg_match('/^\d+$/', $seed)) {
                $this->error('Seeds must be non-negative integers separated by commas.');

                return self::FAILURE;
            }
        }
        $benchmark = $benchmarks->create((string) $this->argument('scenario'), array_map('intval', $seeds));
        $summary = $benchmarks->summary($benchmarks->run($benchmark));
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
