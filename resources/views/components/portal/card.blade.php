@props([
    'padding' => true,
    'variant' => 'default',
])

@php
    $variantClasses = match ($variant) {
        'muted' => 'portal-glass-panel border-white/15 bg-white/8 shadow-md shadow-black/8',
        'raised' => 'portal-glass-panel border-white/20 bg-white/12 shadow-lg shadow-black/10',
        'interactive' => 'portal-glass-panel portal-glass-panel-interactive border-white/20 bg-white/12 shadow-lg shadow-black/10 hover:border-portal-primary/35',
        default => 'portal-glass-panel border-white/18 bg-white/10 shadow-md shadow-black/8',
    };
@endphp

<div
    {{ $attributes->merge([
        'data-glass-interactive' => $variant === 'interactive' ? 'true' : null,
    ])->class([
        'rounded-2xl border portal-transition',
        $variantClasses,
        $padding ? 'p-4 sm:p-5 md:p-6' : '',
    ]) }}
>
    {{ $slot }}
</div>
