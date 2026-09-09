@props([
    'status',
])

@if ($status)
    <div
        {{ $attributes->merge([
            'class' =>
                'rounded-full border border-green-500/35 bg-green-100/90 px-4 py-2.5 text-sm font-medium text-green-800 dark:border-green-500/35 dark:bg-green-950/45 dark:text-green-200',
        ]) }}
        role="status"
    >
        {{ $status }}
    </div>
@endif
