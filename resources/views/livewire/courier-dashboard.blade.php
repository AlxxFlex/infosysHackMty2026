<main wire:poll.1s="poll" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8" x-data="{ showWhy: false }">
    <header class="flex flex-col gap-4 border-b border-slate-800 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Courier AI</p><h1 class="mt-2 text-3xl font-bold tracking-tight">Driver-side optimization</h1><p class="mt-2 text-slate-400">Decisiones de mayor valor, no sólo el pedido con mayor pago.</p><p id="realtime-status" class="mt-2 text-xs text-slate-500">Polling activo · Reverb opcional</p></div>
        <x-ui.badge label="ESCENARIO SIMULADO" tone="amber" />
    </header>

    @if($errorMessage)<div role="alert" class="mt-5 rounded-xl border border-rose-400/40 bg-rose-400/10 p-3 text-sm text-rose-200">{{ $errorMessage }}</div>@endif
    @if($successMessage)<div role="status" class="mt-5 rounded-xl border border-emerald-400/40 bg-emerald-400/10 p-3 text-sm text-emerald-200">{{ $successMessage }}</div>@endif
    @if($eventBanner)<div role="status" class="mt-5 rounded-xl border border-amber-300/40 bg-amber-300/10 p-3 text-sm text-amber-100">{{ $eventBanner }} <span class="text-amber-200/70">(datos simulados)</span></div>@endif
    @if($runId !== null && $status === 'FINISHED')<div class="mt-5"><a href="{{ route('results.show', $runId) }}" class="text-sm text-cyan-200 underline">Ver resultados comparativos del turno</a></div>@endif

    @if($runId === null)
        <section class="mt-8 grid gap-6 lg:grid-cols-[1.3fr_1fr]">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 sm:p-8"><p class="text-sm text-cyan-300">Listo para comenzar</p><h2 class="mt-2 text-2xl font-semibold">Simula un turno reproducible</h2><p class="mt-3 max-w-xl text-slate-400">El motor ejecutará Courier AI y baseline sobre las mismas ofertas. Todos los pagos, rutas y métricas son simulados.</p><div class="mt-7 flex flex-col gap-4 sm:flex-row sm:items-end"><label class="text-sm text-slate-300">Escenario<select wire:model="scenarioKey" class="mt-1 block min-h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 sm:w-56"><option value="demo_normal">Demo normal</option><option value="challenge">Challenge</option></select></label><label class="text-sm text-slate-300">Seed<input wire:model="seed" type="number" min="0" class="mt-1 block min-h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 sm:w-32"></label><x-ui.button wire:click="startShift" wire:loading.attr="disabled"><span wire:loading.remove wire:target="startShift">Iniciar turno</span><span wire:loading wire:target="startShift">Iniciando…</span></x-ui.button></div></div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/50 p-6"><h2 class="font-semibold">Cómo funciona</h2><ol class="mt-4 space-y-3 text-sm text-slate-400"><li><span class="mr-2 text-cyan-300">01</span>Avanza el reloj para publicar ofertas.</li><li><span class="mr-2 text-cyan-300">02</span>Calcula una recomendación determinista.</li><li><span class="mr-2 text-cyan-300">03</span>Acepta y observa las métricas realizadas.</li></ol></div>
        </section>
    @else
        @php($ai = collect($shifts)->firstWhere('agent_type', 'COURIER_AI'))
        @php($baseline = collect($shifts)->firstWhere('agent_type', 'BASELINE'))
        <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4"><x-ui.metric label="Estado" :value="$status" hint="{{ $simulatedCurrentAt ? \Carbon\Carbon::parse($simulatedCurrentAt)->format('H:i') : '—' }} simulado" /><x-ui.metric label="Tiempo restante" :value="$simulatedEndsAt && $simulatedCurrentAt ? max(0, \Carbon\Carbon::parse($simulatedCurrentAt)->diffInMinutes(\Carbon\Carbon::parse($simulatedEndsAt))) . ' min' : '—'" /><x-ui.metric label="Courier AI neto" :value="'$'.($ai['net'] ?? '0.00').' MXN'" hint="{{ $ai['completed'] ?? 0 }} entregas" /><x-ui.metric label="Baseline neto" :value="'$'.($baseline['net'] ?? '0.00').' MXN'" hint="{{ $baseline['completed'] ?? 0 }} entregas" /></section>
        <section class="mt-6 grid gap-6 lg:grid-cols-[1fr_0.9fr]">
            <div class="space-y-6">
                <div class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/40"><div id="courier-map" wire:ignore class="min-h-64 w-full sm:min-h-80" aria-label="Mapa de Courier AI"></div><div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-4 py-3 text-xs text-slate-400"><span id="map-status">Ruta: {{ ($mapData['route']['is_fallback'] ?? true) ? 'estimación fallback' : 'OSRM' }}</span><span><span class="mr-2 inline-block h-2 w-2 rounded-full bg-amber-400"></span>Courier <span class="mx-2 inline-block h-2 w-2 rounded-full bg-cyan-300"></span>Pickup <span class="ml-2 inline-block h-2 w-2 rounded-full bg-pink-400"></span>Dropoff</span></div></div>
                <div class="rounded-3xl border border-slate-800 bg-slate-900/60 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold">Control del turno</h2><p class="text-sm text-slate-400">Reloj virtual con polling idempotente.</p></div><div class="flex flex-wrap gap-2"><x-ui.button variant="secondary" wire:click="pauseShift" wire:loading.attr="disabled">Pausar</x-ui.button><x-ui.button variant="secondary" wire:click="resumeShift" wire:loading.attr="disabled">Reanudar</x-ui.button><x-ui.button wire:click="manualTick" wire:loading.attr="disabled"><span wire:loading.remove wire:target="manualTick">Avanzar {{ $tickMinutes }} min</span><span wire:loading wire:target="manualTick">Avanzando…</span></x-ui.button></div></div></div>
            </div>
            <div class="space-y-6">
                <section class="rounded-3xl border border-cyan-300/30 bg-cyan-400/5 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-xs font-semibold uppercase tracking-wider text-cyan-300">Recomendación</p><h2 class="mt-1 text-xl font-semibold">{{ $recommendation ? 'Plan listo para aceptar' : 'Calcula el siguiente mejor plan' }}</h2></div>
                        @if($recommendation)<button type="button" class="text-sm text-cyan-200 underline" aria-expanded="false" @click="showWhy = !showWhy">¿Por qué?</button>@endif
                    </div>
                    @if($recommendation)
                        @php($planFacts = $recommendation['facts']['plan'] ?? $recommendation['plan'])
                        @php($alternative = $recommendation['facts']['best_rejected_alternative'] ?? null)
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><span class="block text-xs text-slate-500">Neto esperado</span><strong>${{ $planFacts['expected_net_profit_mxn'] ?? '0.00' }} MXN</strong></div>
                            <div><span class="block text-xs text-slate-500">Costo operativo</span><strong>${{ $planFacts['operating_cost_mxn'] ?? '0.00' }} MXN</strong></div>
                            <div><span class="block text-xs text-slate-500">Duración</span><strong>{{ number_format((float) ($planFacts['expected_minutes'] ?? 0), 0) }} min</strong></div>
                            <div><span class="block text-xs text-slate-500">Tasa neta</span><strong>${{ $planFacts['net_hourly_rate_mxn'] ?? '0.00' }}/h</strong></div>
                            <div><span class="block text-xs text-slate-500">Distancia / pickup</span><strong>{{ number_format((float) ($planFacts['expected_distance_km'] ?? 0), 2) }} / {{ number_format((float) ($planFacts['deadhead_distance_km'] ?? 0), 2) }} km</strong></div>
                            <div><span class="block text-xs text-slate-500">Riesgo estimado</span><strong>{{ number_format((float) ($planFacts['risk'] ?? 0) * 100, 0) }}%</strong></div>
                        </div>
                        <div x-show="showWhy" x-cloak class="mt-4 space-y-3 rounded-xl bg-slate-950/60 p-3 text-sm text-slate-300" aria-live="polite">
                            <p><span class="font-semibold text-cyan-200">Fuente:</span> {{ ($recommendation['explanation_source'] ?? 'deterministic') === 'llm' ? 'IA validada' : 'explicación determinista' }} (facts v{{ $recommendation['facts']['version'] ?? '1' }}).</p>
                            <p>{{ $recommendation['llm_explanation'] ?? $recommendation['explanation'] }}</p>
                            @if($alternative)<p><span class="font-semibold text-amber-200">Alternativa:</span> {{ $alternative['order_id'] }} habría dejado ${{ $alternative['difference_vs_selected_net_mxn'] ?? '0.00' }} MXN menos de neto.</p>@endif
                            @if(count($recommendation['plan']['order_ids'] ?? []) > 1)<p><span class="font-semibold text-cyan-200">Batch:</span> ahorra {{ number_format((float) ($planFacts['batch_savings_distance_km'] ?? 0), 2) }} km y comparte {{ number_format((float) ($planFacts['route_overlap'] ?? 0) * 100, 0) }}% de ruta.</p>@endif
                            <p class="text-xs text-slate-500">Ganancia esperada; la ganancia realizada aparece en las métricas del turno después de completar.</p>
                        </div>
                        <x-ui.button class="mt-5 w-full" wire:click="acceptRecommendation" wire:loading.attr="disabled"><span wire:loading.remove wire:target="acceptRecommendation">Aceptar plan</span><span wire:loading wire:target="acceptRecommendation">Aceptando…</span></x-ui.button>
                    @else
                        <x-ui.button class="mt-5 w-full" wire:click="generateRecommendation" wire:loading.attr="disabled"><span wire:loading.remove wire:target="generateRecommendation">Calcular recomendación</span><span wire:loading wire:target="generateRecommendation">Calculando…</span></x-ui.button>
                    @endif
                </section>
            </div>
        </section>
        <section class="mt-6 rounded-3xl border border-slate-800 bg-slate-900/60 p-5"><div class="flex items-end justify-between gap-3"><div><h2 class="text-xl font-semibold">Feed de ofertas</h2><p class="mt-1 text-sm text-slate-400">{{ count($offers) }} ofertas del escenario · las decisiones se calculan en Services.</p></div><span class="text-xs text-slate-500">{{ $status }}</span></div><div class="mt-5 grid gap-3">@forelse($offers as $offer)<x-ui.offer-card :offer="$offer" />@empty<div class="rounded-2xl border border-dashed border-slate-700 p-8 text-center text-sm text-slate-400">Aún no hay pedidos disponibles. Avanza el reloj para publicar el stream.</div>@endforelse</div></section>
    @endif
</main>
