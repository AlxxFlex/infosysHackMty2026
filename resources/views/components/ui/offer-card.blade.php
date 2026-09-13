@props(['offer'])
<article wire:key="offer-{{ $offer['id'] }}" class="rounded-2xl border {{ $offer['recommended'] ? 'border-cyan-300/70 bg-cyan-400/10' : 'border-slate-800 bg-slate-900/80' }} p-4">
    <div class="flex items-start justify-between gap-3">
        <div><p class="text-xs text-slate-400">{{ $offer['id'] }}</p><h3 class="font-semibold text-white">{{ $offer['restaurant'] }}</h3></div>
        @if($offer['recommended'])<x-ui.badge label="AI PICK" tone="cyan" />@endif
    </div>
    <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div><span class="block text-xs text-slate-500">Pago</span><strong>${{ $offer['pay'] }} MXN</strong></div>
        <div><span class="block text-xs text-slate-500">Distancia</span><strong>{{ $offer['distance'] !== null ? number_format($offer['distance'], 1).' km' : '—' }}</strong></div>
        <div><span class="block text-xs text-slate-500">Tiempo</span><strong>{{ $offer['minutes'] !== null ? number_format($offer['minutes'], 0).' min' : '—' }}</strong></div>
        <div><span class="block text-xs text-slate-500">Estado</span><strong>{{ $offer['status'] }}</strong></div>
    </div>
    @if($offer['score'] !== null)<p class="mt-3 text-xs text-slate-400">Neto ${{ $offer['net'] }} MXN · ${{ $offer['hourly'] }}/h · riesgo {{ number_format($offer['risk'] * 100, 0) }}% · score {{ number_format($offer['score'], 0) }}</p>@endif
</article>
