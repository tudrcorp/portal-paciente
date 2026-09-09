@props([
    'value',
])

@php
    $base =
        'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset portal-transition-fast';
@endphp

@if ($value === true)
    <span
        {{ $attributes->class([$base, 'bg-red-100 text-red-800 ring-red-500/15 dark:bg-red-950/55 dark:text-red-100 dark:ring-red-400/25']) }}
    >
        {{ __('Sí') }}
    </span>
@elseif ($value === false)
    <span
        {{ $attributes->class([$base, 'bg-portal-primary-soft text-portal-ink ring-portal-primary/20 dark:bg-portal-primary-soft dark:text-portal-primary dark:ring-portal-primary/25']) }}
    >
        {{ __('No') }}
    </span>
@else
    <span
        {{ $attributes->class([$base, 'bg-portal-subtle text-portal-muted ring-portal-border dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-600']) }}
    >
        {{ __('Sin dato') }}
    </span>
@endif
