@props(['label', 'tone' => 'slate'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold border-'.$tone.'-400/30 bg-'.$tone.'-400/10 text-'.$tone.'-200']) }}>{{ $label }}</span>
