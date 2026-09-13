@props(['label', 'value', 'hint' => null])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-800 bg-slate-900/80 p-4']) }}>
    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ $label }}</p>
    <p class="mt-2 text-xl font-bold text-white">{{ $value }}</p>
    @if($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
</div>
