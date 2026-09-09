@props([
    'label',
    'value',
    'tone' => 'default',
])

@php
    $toneClasses = match ($tone) {
        'alert' => 'border-red-300/35 bg-red-500/10 text-red-100 dark:border-red-400/30 dark:bg-red-950/35 dark:text-red-100',
        'warning' => 'border-amber-300/35 bg-amber-500/10 text-amber-100 dark:border-amber-400/30 dark:bg-amber-950/35 dark:text-amber-100',
        default => '',
    };
@endphp

<div {{ $attributes->class(['portal-liquid-section rounded-xl px-4 py-3', $toneClasses]) }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold text-portal-ink dark:text-zinc-100">{{ $value }}</p>
</div>
