@props([
    'variant' => 'default', // default | ambient | pure
    'interactive' => false,
    'padding' => true,
])

@php
    $rootClasses = [
        'portal-glass-panel',
        'relative isolate overflow-hidden',
        $interactive ? 'portal-glass-panel-interactive cursor-pointer' : '',
        $padding ? 'p-4 sm:p-5 md:p-6' : '',
    ];

    $panelId = $attributes->get('id');
    $wireKey = $panelId ? 'glass-'.md5((string) $panelId) : null;
    $showAmbient = $variant !== 'pure';
    $interactiveAttrs = $interactive ? ['data-glass-interactive' => 'true'] : [];
@endphp

<div
    {{ $attributes->merge($interactiveAttrs)->class($rootClasses) }}
    @if ($wireKey) wire:key="{{ $wireKey }}" @endif
>
    @if ($showAmbient)
        <div class="portal-glass-ambient absolute inset-0 -z-20 pointer-events-none select-none" wire:ignore>
            <div class="portal-liquid-blob portal-liquid-blob-a"></div>
            <div class="portal-liquid-blob portal-liquid-blob-b"></div>
        </div>
    @endif

    <div class="portal-glass-refractor absolute inset-0 -z-10 pointer-events-none" wire:ignore>
        <div class="portal-glass-specular"></div>
    </div>

    <div class="portal-glass-content relative z-10">
        {{ $slot }}
    </div>
</div>
