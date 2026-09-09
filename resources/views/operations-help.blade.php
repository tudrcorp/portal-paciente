<x-layouts.app :title="__('Ayuda')">
    <div class="portal-stack-appear portal-typography-fluid mx-auto flex w-full max-w-[1220px] flex-col gap-6 motion-safe:animate-portal-fade xl:gap-8">
        <header class="flex flex-col gap-4 border-b border-portal-border-soft py-6 dark:border-zinc-700">
            <div class="flex items-start gap-4">
                <span
                    class="mt-0.5 flex size-12 shrink-0 items-center justify-center rounded-2xl bg-portal-primary-soft text-portal-primary ring-1 ring-portal-primary/25 dark:bg-portal-primary-soft dark:text-portal-primary"
                    aria-hidden="true"
                >
                    <flux:icon name="lifebuoy" class="size-7" />
                </span>
                <div class="min-w-0">
                    <flux:heading size="xl" class="font-semibold tracking-tight text-portal-ink dark:text-zinc-100">
                        {{ __('Centro de ayuda') }}
                    </flux:heading>
                    <flux:subheading class="mt-1 max-w-2xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                        {{ __('Contacta al equipo de operaciones por WhatsApp. Elige a quien prefieras y te abriremos el chat con un mensaje listo para enviar.') }}
                    </flux:subheading>
                </div>
            </div>
        </header>

        @if ($isGuest ?? false)
            <x-portal.alert variant="info" :title="__('Recuperación de clave')">
                {{ __('Si olvidaste tu clave, escribe a cualquiera de estos colaboradores por WhatsApp. Ellos te ayudarán a actualizarla en el sistema.') }}
            </x-portal.alert>
        @elseif (! $isPatient)
            <x-portal.alert variant="warning" :title="__('Cuenta no vinculada a paciente')">
                {{ __('Puedes ver los contactos del equipo, pero la experiencia está optimizada para pacientes autenticados.') }}
            </x-portal.alert>
        @endif

        <x-glass-panel variant="ambient" class="!p-4 sm:!p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#25D366]/15 text-[#128C7E] ring-1 ring-[#25D366]/30"
                        aria-hidden="true"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.884 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-portal-ink dark:text-zinc-100">
                            {{ __('¿Cómo funciona?') }}
                        </p>
                        <p class="mt-1 text-sm leading-relaxed text-portal-muted dark:text-zinc-400">
                            {{ __('Toca «Escribir por WhatsApp» en móvil o haz clic en escritorio. Se abrirá WhatsApp con un mensaje inicial; solo envíalo para iniciar la conversación.') }}
                        </p>
                    </div>
                </div>

                <flux:badge color="sky" size="sm" class="self-start sm:self-center">
                    {{ trans_choice(':count colaborador disponible|:count colaboradores disponibles', count($contacts), ['count' => count($contacts)]) }}
                </flux:badge>
            </div>
        </x-glass-panel>

        @if (count($contacts) === 0)
            <x-portal.empty-state
                class="!py-12"
                :title="__('No hay contactos disponibles por ahora')"
                :description="__('El equipo de operaciones no ha publicado contactos de ayuda en este momento. Intenta más tarde o comunícate por los canales habituales de tu afiliación.')"
            >
                <x-slot name="icon">
                    <flux:icon name="phone-x-mark" class="size-7 text-portal-primary dark:text-portal-primary" />
                </x-slot>
            </x-portal.empty-state>
        @else
            <section aria-labelledby="operations-team-heading">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="operations-team-heading" class="text-sm font-semibold uppercase tracking-[0.08em] text-portal-muted dark:text-zinc-400">
                        {{ __('Equipo de operaciones') }}
                    </h2>
                    <p class="text-xs text-portal-muted dark:text-zinc-500">
                        {{ __('Contactos publicados por operaciones') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($contacts as $contact)
                        <x-portal.operations-contact-card
                            :name="$contact['name']"
                            :initials="$contact['initials']"
                            :phone-display="$contact['phone_display']"
                            :whatsapp-url="$contact['whatsapp_url']"
                        />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
