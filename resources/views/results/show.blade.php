<x-layouts.app title="Courier AI — Resultados">
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="border-b border-slate-800 pb-6">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Courier AI</p>
            <h1 class="mt-2 text-3xl font-bold">Resultados del simulador controlado</h1>
            <p class="mt-2 text-slate-400">Escenario {{ $summary['scenario_key'] }} · seed {{ $summary['seed'] }} · configuración {{ $summary['config_version'] }}</p>
        </header>

        @php($agents = $summary['agents'])
        <section class="mt-8 grid gap-5 md:grid-cols-2">
            @foreach(['COURIER_AI' => 'Courier AI', 'BASELINE' => 'Baseline'] as $key => $label)
                @php($metrics = $agents[$key] ?? [])
                <article class="rounded-3xl border {{ $key === 'COURIER_AI' ? 'border-cyan-300/30 bg-cyan-400/5' : 'border-slate-800 bg-slate-900/60' }} p-6">
                    <h2 class="text-xl font-semibold">{{ $label }}</h2>
                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-slate-500">Ganancia bruta</dt><dd class="font-semibold">${{ $metrics['gross_earnings_mxn'] ?? '0.00' }} MXN</dd></div>
                        <div><dt class="text-slate-500">Costo operativo</dt><dd class="font-semibold">${{ $metrics['operating_cost_mxn'] ?? '0.00' }} MXN</dd></div>
                        <div><dt class="text-slate-500">Ganancia neta realizada</dt><dd class="font-semibold">${{ $metrics['net_earnings_mxn'] ?? '0.00' }} MXN</dd></div>
                        <div><dt class="text-slate-500">MXN/h comparable</dt><dd class="font-semibold">${{ $metrics['net_hourly_rate_mxn'] ?? '0.00' }}</dd></div>
                        <div><dt class="text-slate-500">Kilómetros / deadhead</dt><dd class="font-semibold">{{ number_format((float) ($metrics['total_distance_km'] ?? 0), 2) }} / {{ number_format((float) ($metrics['deadhead_distance_km'] ?? 0), 2) }}</dd></div>
                        <div><dt class="text-slate-500">Activo / idle</dt><dd class="font-semibold">{{ $metrics['active_minutes'] ?? 0 }} / {{ $metrics['idle_minutes'] ?? 0 }} min</dd></div>
                        <div><dt class="text-slate-500">Aceptados / rechazados</dt><dd class="font-semibold">{{ $metrics['accepted_orders_count'] ?? 0 }} / {{ $metrics['rejected_orders_count'] ?? 0 }}</dd></div>
                        <div><dt class="text-slate-500">Completados / tardíos</dt><dd class="font-semibold">{{ $metrics['completed_orders_count'] ?? 0 }} / {{ $metrics['late_orders_count'] ?? 0 }}</dd></div>
                    </dl>
                    <p class="mt-5 text-sm text-slate-400">Tiempo productivo: {{ number_format((float) ($metrics['productive_time_percent'] ?? 0), 2) }}%</p>
                </article>
            @endforeach
        </section>

        <section class="mt-6 rounded-3xl border border-amber-300/30 bg-amber-300/5 p-6">
            <h2 class="text-lg font-semibold">Comparación</h2>
            @if($summary['improvement_percent'] === null)
                <p class="mt-2 text-sm text-amber-100">Baseline neto = $0.00 MXN; el porcentaje de mejora no se define para evitar división entre cero.</p>
            @else
                <p class="mt-2 text-2xl font-bold {{ $summary['improvement_percent'] >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $summary['improvement_percent'] > 0 ? '+' : '' }}{{ number_format($summary['improvement_percent'], 2) }}%</p>
                <p class="mt-1 text-sm text-slate-400">(Courier AI neto − Baseline neto) / Baseline neto × 100</p>
            @endif
        </section>

        @if($benchmarkSummary)
            <section class="mt-6 rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <h2 class="text-xl font-semibold">Benchmark multi-seed</h2>
                <p class="mt-1 text-sm text-slate-400">{{ $benchmarkSummary['completed_seeds'] }} seeds completas · {{ $benchmarkSummary['failed_seeds'] }} fallidas @if($benchmarkSummary['failed_seed_ids']) (fallidas: {{ implode(', ', $benchmarkSummary['failed_seed_ids']) }}) @endif · win rate Courier AI {{ $benchmarkSummary['courier_ai_win_rate_percent'] === null ? 'n/d' : number_format($benchmarkSummary['courier_ai_win_rate_percent'], 2).'%' }}</p>
                <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[38rem] text-left text-sm"><thead class="text-slate-500"><tr><th class="py-2">Seed</th><th class="py-2">Courier AI neto</th><th class="py-2">Baseline neto</th><th class="py-2">Mejora</th><th class="py-2">Ganador</th></tr></thead><tbody>@foreach($benchmarkSummary['comparisons'] as $comparison)<tr class="border-t border-slate-800"><td class="py-2">{{ $comparison['seed'] }}</td><td class="py-2">${{ $comparison['courier_ai_net_profit'] }}</td><td class="py-2">${{ $comparison['baseline_net_profit'] }}</td><td class="py-2">{{ $comparison['improvement_percent'] === null ? 'n/d' : number_format($comparison['improvement_percent'], 2).'%' }}</td><td class="py-2">{{ $comparison['winner'] }}</td></tr>@endforeach</tbody></table></div>
                @if($benchmarkSummary['failed_details'])<div class="mt-4 space-y-1 text-sm text-rose-200">@foreach($benchmarkSummary['failed_details'] as $seed => $error)<p>Seed {{ $seed }} fallida: {{ $error }}</p>@endforeach</div>@endif
            </section>
        @endif
        <p class="mt-6 text-xs text-slate-500">Todos los valores son simulados, reproducibles y calculados desde deliveries/shifts realizados.</p>
        <a class="mt-4 inline-block text-sm text-cyan-200 underline" href="{{ route('courier') }}">Volver al dashboard</a>
    </main>
</x-layouts.app>
