@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $scheme = match ($variant) {
        'success' => [
            'wrap' => 'portal-glass-panel border-portal-border/55 bg-portal-success-soft/70 text-portal-ink dark:border-portal-success/25 dark:bg-portal-success-soft',
            'icon' => 'text-portal-success',
            'title' => 'text-portal-success',
        ],
        'warning' => [
            'wrap' => 'portal-glass-panel border-amber-200/55 bg-amber-100/35 text-portal-ink dark:border-amber-400/25 dark:bg-amber-500/8',
            'icon' => 'text-portal-warning',
            'title' => 'text-portal-warning',
        ],
        'danger' => [
            'wrap' => 'portal-glass-panel border-red-200/55 bg-red-100/35 text-portal-ink dark:border-red-400/25 dark:bg-red-500/8',
            'icon' => 'text-portal-danger',
            'title' => 'text-portal-danger',
        ],
        default => [
            'wrap' => 'portal-glass-panel border-portal-border/55 bg-portal-info-soft/70 text-portal-ink dark:border-portal-primary/25 dark:bg-portal-primary-soft',
            'icon' => 'text-portal-info',
            'title' => 'text-portal-info',
        ],
    };
@endphp

<div
    role="alert"
    {{ $attributes->class([
        'portal-transition-fast flex gap-3 rounded-2xl border px-4 py-3 text-sm leading-relaxed',
        $scheme['wrap'],
    ]) }}
>
    <div class="mt-0.5 shrink-0 font-semibold {{ $scheme['icon'] }}" aria-hidden="true">
        @switch($variant)
            @case('success')
                ✓
                @break
            @case('warning')
                !
                @break
            @case('danger')
                ×
                @break
            @default
                i
        @endswitch
    </div>
    <div class="min-w-0 flex-1 space-y-1">
        @if ($title)
            <p class="font-semibold {{ $scheme['title'] }}">{{ $title }}</p>
        @endif
        <div class="text-portal-ink/90 [&_a]:font-medium [&_a]:text-portal-primary [&_a]:underline-offset-2 [&_a]:hover:text-portal-primary-hover">
            {{ $slot }}
        </div>
    </div>
</div>
