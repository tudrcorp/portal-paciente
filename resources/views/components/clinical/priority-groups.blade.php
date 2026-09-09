@props([
    'groups',
    'empty' => null,
])

@php
    $hasItems = collect($groups)->flatten()->isNotEmpty();
    $priorityMeta = [
        'alta' => ['color' => 'red', 'label' => __('Alta')],
        'media' => ['color' => 'amber', 'label' => __('Media')],
        'baja' => ['color' => 'zinc', 'label' => __('Baja')],
    ];
@endphp

@if ($hasItems)
    <div {{ $attributes->class(['space-y-3']) }}>
        @foreach ($priorityMeta as $level => $meta)
            @continue(count($groups[$level] ?? []) === 0)
            <div class="rounded-xl border border-portal-border-soft/80 bg-portal-surface/55 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900/45">
                <div class="mb-1.5 flex items-center gap-2">
                    <flux:badge :color="$meta['color']" size="sm">{{ __('Prioridad :level', ['level' => $meta['label']]) }}</flux:badge>
                </div>
                <ul class="list-disc space-y-1 pl-4 text-sm text-portal-ink dark:text-zinc-200">
                    @foreach ($groups[$level] as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-portal-muted dark:text-zinc-400">
        {{ $empty ?? __('No hay información registrada en esta sección.') }}
    </p>
@endif
