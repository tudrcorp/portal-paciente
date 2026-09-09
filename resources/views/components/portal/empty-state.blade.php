@props([
    'title',
    'description' => null,
])

<div
    {{ $attributes->class([
        'portal-glass-panel flex flex-col items-center justify-center rounded-2xl border border-dashed border-white/25 bg-white/10 px-6 py-14 text-center portal-transition sm:px-10 sm:py-16',
    ]) }}
>
    @if (isset($icon) && $icon->isNotEmpty())
        <div
            class="mb-4 flex size-14 items-center justify-center rounded-2xl border border-white/25 bg-white/20 text-2xl text-portal-primary shadow-sm ring-1 ring-white/20 motion-safe:animate-portal-fade"
            aria-hidden="true"
        >
            {{ $icon }}
        </div>
    @endif

    <h3 class="max-w-sm text-balance text-lg font-semibold tracking-tight text-portal-ink">
        {{ $title }}
    </h3>

    @if ($description)
        <p class="mt-2 max-w-md text-pretty text-sm leading-relaxed text-portal-muted">
            {{ $description }}
        </p>
    @endif

    @if (isset($actions) && $actions->isNotEmpty())
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            {{ $actions }}
        </div>
    @endif
</div>
