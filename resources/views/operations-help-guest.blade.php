<x-layouts.auth :title="__('Ayuda')">
    <div class="mx-auto flex w-full max-w-lg flex-col gap-5">
        <div>
            <flux:heading size="lg" class="font-semibold text-portal-ink dark:text-zinc-100">
                {{ __('¿Olvidaste tu clave?') }}
            </flux:heading>
            <flux:subheading class="mt-1 text-sm leading-relaxed text-portal-muted dark:text-zinc-400">
                {{ __('Escribe a Operaciones por WhatsApp. Ellos actualizarán tu clave en el sistema para que puedas entrar al portal.') }}
            </flux:subheading>
        </div>

        @if (count($contacts) === 0)
            <x-portal.alert variant="warning" :title="__('Sin contactos disponibles')">
                {{ __('No hay teléfonos de Operaciones disponibles ahora. Intenta más tarde o usa el botón de WhatsApp del login si aparece.') }}
            </x-portal.alert>
        @else
            <div class="grid gap-3">
                @foreach ($contacts as $contact)
                    <x-portal.operations-contact-card
                        :name="$contact['name']"
                        :initials="$contact['initials']"
                        :phone-display="$contact['phone_display']"
                        :whatsapp-url="$contact['whatsapp_url']"
                    />
                @endforeach
            </div>
        @endif

        <a
            href="{{ route('login') }}"
            class="text-center text-sm font-medium text-portal-primary underline-offset-2 hover:underline"
            wire:navigate
        >
            {{ __('Volver al inicio de sesión') }}
        </a>
    </div>
</x-layouts.auth>
