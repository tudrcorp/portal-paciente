@props([
    'label',
    'content' => null,
])

@if (filled($content))
    <div {{ $attributes->class(['rounded-xl border border-portal-border-soft bg-portal-subtle/60 p-4 dark:border-zinc-700 dark:bg-zinc-900/40']) }}>
        <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">
            {{ $label }}
        </p>
        <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-portal-ink dark:text-zinc-100">
            {{ $content }}
        </div>
    </div>
@endif
