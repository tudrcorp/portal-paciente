@props([
    'href',
    'title',
    'icon',
    'tone' => 'documents',
    'hint' => null,
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class([
        'portal-access-tile',
        'portal-access-tile--'.$tone,
    ]) }}
>
    <span class="portal-access-tile__sheen" aria-hidden="true"></span>
    <span class="portal-access-tile__glow" aria-hidden="true"></span>

    <span class="portal-access-tile__top">
        <span class="portal-access-tile__icon" aria-hidden="true">
            <flux:icon :name="$icon" class="size-5" />
        </span>
        <span class="portal-access-tile__arrow" aria-hidden="true">
            <flux:icon name="arrow-up-right" variant="mini" class="size-3.5" />
        </span>
    </span>

    <span class="portal-access-tile__copy">
        <span class="portal-access-tile__title">{{ $title }}</span>
        @if (filled($hint))
            <span class="portal-access-tile__hint">{{ $hint }}</span>
        @endif
    </span>
</a>
