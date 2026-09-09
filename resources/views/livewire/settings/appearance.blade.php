<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        :heading="__('Apariencia')"
        :subheading="__('Usa el toggle de la esquina superior derecha para cambiar entre tema claro y oscuro.')"
    >
        <x-portal.card>
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-portal-primary-soft text-portal-primary">
                    <flux:icon name="sun" class="size-5" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-portal-ink dark:text-zinc-100">{{ __('Control de tema') }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-portal-muted dark:text-zinc-400">
                        {{ __('Activa el sol para modo claro o la luna para modo oscuro. Tu elección se guarda automáticamente en este dispositivo.') }}
                    </p>
                </div>
            </div>
        </x-portal.card>
    </x-settings.layout>
</section>
