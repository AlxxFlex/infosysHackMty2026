<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="border-b border-slate-800 pb-6">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Courier AI · Demo</p>
        <h1 class="mt-2 text-3xl font-bold">Panel de demo controlada</h1>
        <p class="mt-2 text-slate-400">Historia reproducible en menos de tres minutos. Todos los valores son simulados.</p>
    </header>

    @if($error)<div role="alert" class="mt-5 rounded-xl border border-rose-400/40 bg-rose-400/10 p-3 text-sm text-rose-200">{{ $error }}</div>@endif
    @if($message)<div role="status" class="mt-5 rounded-xl border border-emerald-400/40 bg-emerald-400/10 p-3 text-sm text-emerald-200">{{ $message }}</div>@endif

    <section class="mt-6 grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-3xl border border-cyan-300/30 bg-cyan-400/5 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs uppercase tracking-wider text-cyan-300">Run controlado</p><p class="mt-1 text-2xl font-semibold">{{ $runId ?: 'Sin iniciar' }}</p></div><span class="rounded-full bg-amber-300/20 px-3 py-1 text-xs text-amber-100">{{ $status }}</span></div>
            <dl class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt class="text-slate-500">Seed</dt><dd class="font-semibold">{{ $seed }}</dd></div><div><dt class="text-slate-500">Ofertas preparadas</dt><dd class="font-semibold">{{ $offersCount }}</dd></div><div><dt class="text-slate-500">Reloj simulado</dt><dd class="font-semibold">{{ $currentAt ?: '—' }}</dd></div><div><dt class="text-slate-500">Velocidad</dt><dd class="font-semibold">x{{ $speed }}</dd></div></dl>
            <div class="mt-6 flex flex-wrap gap-2">
                <button type="button" wire:click="start" wire:loading.attr="disabled" class="rounded-xl bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50">Iniciar escenario</button>
                <button type="button" wire:click="newSeed" wire:loading.attr="disabled" @disabled($runId !== 0) class="rounded-xl border border-slate-700 px-4 py-2 text-sm disabled:opacity-50">Nueva seed</button>
                <button type="button" wire:click="pause" wire:loading.attr="disabled" @disabled($status !== 'RUNNING') class="rounded-xl border border-slate-700 px-4 py-2 text-sm disabled:opacity-50">Pausar</button>
                <button type="button" wire:click="resume" wire:loading.attr="disabled" @disabled($status !== 'PAUSED') class="rounded-xl border border-slate-700 px-4 py-2 text-sm disabled:opacity-50">Reanudar</button>
                <button type="button" wire:click="tick" wire:loading.attr="disabled" @disabled($status !== 'RUNNING') class="rounded-xl border border-slate-700 px-4 py-2 text-sm disabled:opacity-50">Avanzar tick</button>
                @foreach([1, 5, 20] as $multiplier)<button type="button" wire:click="setSpeed({{ $multiplier }})" wire:loading.attr="disabled" @disabled($runId === 0) class="rounded-xl border {{ $speed === $multiplier ? 'border-cyan-300 text-cyan-200' : 'border-slate-700 text-slate-300' }} px-3 py-2 text-xs disabled:opacity-50">x{{ $multiplier }}</button>@endforeach
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="triggerSurge" wire:loading.attr="disabled" @disabled($status !== 'RUNNING') class="rounded-xl border border-amber-300/40 px-3 py-2 text-sm text-amber-100 disabled:opacity-50">Activar surge</button>
                <button type="button" wire:click="triggerClosure" wire:loading.attr="disabled" @disabled($status !== 'RUNNING') class="rounded-xl border border-rose-300/40 px-3 py-2 text-sm text-rose-100 disabled:opacity-50">Activar cierre</button>
                <button type="button" wire:click="addRestaurantDelay" wire:loading.attr="disabled" @disabled($status !== 'RUNNING') class="rounded-xl border border-violet-300/40 px-3 py-2 text-sm text-violet-100 disabled:opacity-50">Retraso restaurante</button>
                <button type="button" wire:click="finish" wire:loading.attr="disabled" @disabled(! in_array($status, ['RUNNING', 'PAUSED'])) class="rounded-xl border border-emerald-300/40 px-3 py-2 text-sm text-emerald-100 disabled:opacity-50">Terminar turno</button>
            </div>
            @if($runId && $status === 'FINISHED')<a class="mt-5 inline-block text-sm text-cyan-200 underline" href="{{ route('results.show', $runId) }}">Abrir comparación de resultados</a>@endif
        </div>
        <aside class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6"><h2 class="text-lg font-semibold">Guion visible</h2><ol class="mt-4 space-y-3 text-sm text-slate-400"><li><span class="mr-2 text-cyan-300">1.</span>Iniciar turno con cinco+ ofertas.</li><li><span class="mr-2 text-cyan-300">2.</span>Comparar ofertas y abrir ¿Por qué?</li><li><span class="mr-2 text-cyan-300">3.</span>Aceptar/avanzar el reloj.</li><li><span class="mr-2 text-cyan-300">4.</span>Activar cierre o surge.</li><li><span class="mr-2 text-cyan-300">5.</span>Observar reoptimización.</li><li><span class="mr-2 text-cyan-300">6.</span>Finalizar y comparar.</li></ol><h2 class="mt-6 text-sm font-semibold uppercase tracking-wider text-slate-500">Auditoría local</h2><ul class="mt-2 space-y-1 text-xs text-slate-500">@forelse($audit as $entry)<li>{{ $entry }}</li>@empty<li>Sin acciones todavía.</li>@endforelse</ul></aside>
    </section>
    @if($confirmReset)<div class="mt-5 rounded-2xl border border-rose-300/40 bg-rose-300/10 p-4 text-sm text-rose-100" role="alert"><p>Esto eliminará sólo el run {{ $runId }} visible. ¿Confirmas?</p><button type="button" wire:click="resetRun" wire:loading.attr="disabled" class="mt-3 rounded-xl bg-rose-300 px-4 py-2 font-semibold text-slate-950">Confirmar reset</button><button type="button" wire:click="$set('confirmReset', false)" class="ml-2 text-rose-100 underline">Cancelar</button></div>@endif
    @if($runId)<button type="button" wire:click="requestReset" wire:loading.attr="disabled" class="mt-5 text-xs text-rose-300 underline">Resetear run actual</button>@endif
</main>
