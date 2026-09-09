<x-portal.card variant="raised">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ $reminder['title'] }}</p>
                @if ($reminder['is_active'])
                    <span class="rounded-full bg-portal-success-soft px-2.5 py-0.5 text-xs font-semibold text-portal-success">{{ __('Activo') }}</span>
                @else
                    <span class="rounded-full bg-zinc-200/70 px-2.5 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ __('Pausado') }}</span>
                @endif
            </div>
            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">{{ $reminder['summary'] }}</p>
            @if (! empty($reminder['channels_labels']))
                <p class="mt-2 text-xs font-medium uppercase tracking-wide text-portal-muted dark:text-zinc-500">
                    {{ implode(' · ', $reminder['channels_labels']) }}
                </p>
            @endif
            @if (! empty($reminder['notes']))
                <p class="mt-2 text-sm text-portal-ink/80 dark:text-zinc-300">{{ $reminder['notes'] }}</p>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button type="button" size="sm" variant="ghost" icon="pencil-square" @click="openEdit({{ $reminder['id'] }})">
                {{ __('Editar') }}
            </flux:button>

            <form method="POST" action="{{ route('notifications.toggle', $reminder['id']) }}">
                @csrf
                @method('PATCH')
                <flux:button type="submit" size="sm" variant="ghost" icon="{{ $reminder['is_active'] ? 'pause' : 'play' }}">
                    {{ $reminder['is_active'] ? __('Pausar') : __('Activar') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('notifications.destroy', $reminder['id']) }}" onsubmit="return confirm(@js(__('¿Eliminar este recordatorio?')))">
                @csrf
                @method('DELETE')
                <flux:button type="submit" size="sm" variant="danger" icon="trash">
                    {{ __('Eliminar') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-portal.card>
