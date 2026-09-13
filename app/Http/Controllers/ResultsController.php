<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BenchmarkRun;
use App\Models\SimulationRun;
use App\Services\BenchmarkService;
use Illuminate\View\View;

final class ResultsController extends Controller
{
    public function show(SimulationRun $run, BenchmarkService $benchmarks): View
    {
        $benchmark = BenchmarkRun::query()->whereHas('results', fn ($query) => $query->where('simulation_run_id', $run->id))->latest('id')->first();

        return view('results.show', [
            'run' => $run->load('shifts'),
            'summary' => $benchmarks->simulationSummary($run),
            'benchmark' => $benchmark,
            'benchmarkSummary' => $benchmark === null ? null : $benchmarks->summary($benchmark),
        ]);
    }
}
