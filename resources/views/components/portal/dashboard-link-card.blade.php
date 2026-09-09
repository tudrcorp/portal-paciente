@props([
    'href',
    'title',
    'description',
    'icon',
    'tone' => 'documents',
    'cta' => null,
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class([
        'portal-dash-card',
        'portal-dash-card--'.$tone,
    ]) }}
>
    <span class="portal-dash-card__glow" aria-hidden="true"></span>

    <span class="portal-dash-card__top">
        <span class="portal-dash-card__icon" aria-hidden="true">
            <flux:icon :name="$icon" class="size-6" />
        </span>
        <span class="portal-dash-card__arrow" aria-hidden="true">
            <flux:icon name="arrow-right" variant="mini" class="size-4" />
        </span>
    </span>

    <span class="portal-dash-card__body">
        <span class="portal-dash-card__title">{{ $title }}</span>
        <span class="portal-dash-card__description">{{ $description }}</span>
    </span>

    <span class="portal-dash-card__cta">
        {{ $cta ?? __('Abrir sección') }}
    </span>
</a>
