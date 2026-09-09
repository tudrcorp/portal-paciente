<x-layouts.app :title="__('Notificaciones')">
    @php
        $dayLabels = $dayLabels ?? [];
        $activeTab = $activeTab ?? 'chronic';
        $notificationTabs = [
            ['id' => 'chronic', 'label' => __('Tratamiento crónico'), 'icon' => 'beaker'],
            ['id' => 'specific', 'label' => __('Tratamiento específico'), 'icon' => 'clipboard-document-list'],
            ['id' => 'appointments', 'label' => __('Citas médicas'), 'icon' => 'calendar-days'],
            ['id' => 'history', 'label' => __('Historial'), 'icon' => 'clock'],
        ];
    @endphp

    <div
        x-data="{
            tab: @js($activeTab),
            createOpen: false,
            createType: 'chronic_treatment',
            editingId: null,
            segReady: false,
            setTab(name) {
                this.tab = name;
                this.createOpen = false;
                this.editingId = null;
                this.$nextTick(() => this.setIndicator());
            },
            openCreate(type) {
                this.createType = type;
                this.createOpen = true;
                this.editingId = null;
                this.tab = type === 'appointment' ? 'appointments' : (type === 'specific_treatment' ? 'specific' : 'chronic');
                this.$nextTick(() => this.setIndicator());
            },
            openEdit(id) { this.editingId = id; this.createOpen = false; },
            setIndicator() {
                const link = this.$root.querySelector(`[data-tab='${this.tab}']`);
                if (!link || !this.$refs.tabNav) return;
                const nav = this.$refs.tabNav;
                nav.style.setProperty('--seg-x', `${link.offsetLeft}px`);
                nav.style.setProperty('--seg-w', `${link.offsetWidth}px`);
                this.segReady = true;
            },
            init() {
                this.$nextTick(() => this.setIndicator());
                window.addEventListener('resize', () => this.setIndicator());
            }
        }"
        class="portal-stack-appear portal-typography-fluid mx-auto flex w-full max-w-[1220px] flex-col gap-6 motion-safe:animate-portal-fade xl:gap-8"
    >
        <header class="flex flex-col gap-4 border-b border-portal-border-soft py-6 dark:border-zinc-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span
                        class="mt-0.5 flex size-12 shrink-0 items-center justify-center rounded-2xl bg-portal-primary-soft text-portal-primary ring-1 ring-portal-primary/25 dark:bg-portal-primary-soft dark:text-portal-primary"
                        aria-hidden="true"
                    >
                        <flux:icon name="bell" class="size-7" />
                    </span>
                    <div>
                        <flux:heading size="xl" class="font-semibold tracking-tight text-portal-ink dark:text-zinc-100">
                            {{ __('Notificaciones al paciente') }}
                        </flux:heading>
                        <flux:subheading class="mt-1 max-w-3xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                            {{ __('Configura alarmas para tratamientos crónicos, tratamientos específicos y recordatorios de citas médicas.') }}
                        </flux:subheading>
                    </div>
                </div>
            </div>
        </header>

        @if (session('portal.notification_success'))
            <x-portal.alert variant="success" :title="__('Listo')">
                {{ session('portal.notification_success') }}
            </x-portal.alert>
        @endif

        @if ($errors->any())
            <x-portal.alert variant="danger" :title="__('Revisa el formulario')">
                <ul class="list-disc ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-portal.alert>
        @endif

        @if (! $isPatient)
            <x-portal.alert variant="warning" :title="__('Cuenta no vinculada a paciente')">
                {{ __('Esta vista solo está disponible para cuentas de pacientes autenticadas desde la base clínica.') }}
            </x-portal.alert>
        @else
            @if (! ($data['has_phone'] ?? false))
                <x-portal.alert variant="warning" :title="__('WhatsApp no disponible')">
                    {{ __('No encontramos un teléfono válido en tu perfil. Puedes usar avisos in-app, SMS o Push, pero WhatsApp quedará desactivado hasta actualizar tu contacto.') }}
                </x-portal.alert>
            @else
                <x-portal.alert variant="info" :title="__('Canal WhatsApp')">
                    {{ __('Los avisos de WhatsApp se enviarán a') }}
                    <strong>{{ $data['phone_display'] }}</strong>.
                </x-portal.alert>
            @endif

            <nav
                x-ref="tabNav"
                class="portal-segmented-nav portal-segmented-nav-enhanced -mx-1"
                :data-seg-ready="segReady ? 'true' : 'false'"
                aria-label="{{ __('Secciones de notificaciones') }}"
                role="tablist"
            >
                <div class="portal-segmented-track">
                    <span class="portal-segmented-indicator" aria-hidden="true"></span>
                    @foreach ($notificationTabs as $tabItem)
                        <button
                            type="button"
                            role="tab"
                            data-tab="{{ $tabItem['id'] }}"
                            :aria-selected="tab === @js($tabItem['id'])"
                            :class="tab === @js($tabItem['id']) ? 'portal-segmented-link-active' : ''"
                            class="portal-segmented-link"
                            @click="setTab(@js($tabItem['id']))"
                        >
                            <flux:icon :icon="$tabItem['icon']" class="size-4 shrink-0" />
                            <span>{{ $tabItem['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </nav>

            {{-- Chronic --}}
            <section x-show="tab === 'chronic'" x-cloak class="space-y-4">
                <div class="flex justify-end">
                    <flux:button variant="primary" icon="plus" class="portal-glass-button" type="button" @click="openCreate('chronic_treatment')">
                        {{ __('Nueva alarma crónica') }}
                    </flux:button>
                </div>

                <div x-show="createOpen && createType === 'chronic_treatment'" x-cloak>
                    @include('partials.notifications.reminder-form', [
                        'formType' => 'chronic_treatment',
                        'action' => route('notifications.store'),
                        'method' => 'POST',
                        'reminder' => null,
                        'dayLabels' => $dayLabels,
                        'hasPhone' => $data['has_phone'] ?? false,
                    ])
                </div>

                @forelse ($data['chronic'] as $reminder)
                    <div x-show="editingId === {{ $reminder['id'] }}" x-cloak>
                        @include('partials.notifications.reminder-form', [
                            'formType' => 'chronic_treatment',
                            'action' => route('notifications.update', $reminder['id']),
                            'method' => 'PUT',
                            'reminder' => $reminder,
                            'dayLabels' => $dayLabels,
                            'hasPhone' => $data['has_phone'] ?? false,
                        ])
                    </div>
                    <div x-show="editingId !== {{ $reminder['id'] }}">
                        @include('partials.notifications.reminder-card', ['reminder' => $reminder])
                    </div>
                @empty
                    <x-portal.empty-state
                        class="!py-10"
                        :title="__('Sin alarmas crónicas')"
                        :description="__('Crea una alarma recurrente para tu tratamiento de largo plazo (por ejemplo, cada día a las 8:00 y 20:00).')"
                    >
                        <x-slot name="icon">
                            <flux:icon name="bell" class="size-7 text-portal-primary dark:text-portal-primary" />
                        </x-slot>
                    </x-portal.empty-state>
                @endforelse
            </section>

            {{-- Specific --}}
            <section x-show="tab === 'specific'" x-cloak class="space-y-4">
                <div class="flex justify-end">
                    <flux:button variant="primary" icon="plus" class="portal-glass-button" type="button" @click="openCreate('specific_treatment')">
                        {{ __('Nuevo tratamiento específico') }}
                    </flux:button>
                </div>

                <div x-show="createOpen && createType === 'specific_treatment'" x-cloak>
                    @include('partials.notifications.reminder-form', [
                        'formType' => 'specific_treatment',
                        'action' => route('notifications.store'),
                        'method' => 'POST',
                        'reminder' => null,
                        'dayLabels' => $dayLabels,
                        'hasPhone' => $data['has_phone'] ?? false,
                    ])
                </div>

                @forelse ($data['specific'] as $reminder)
                    <div x-show="editingId === {{ $reminder['id'] }}" x-cloak>
                        @include('partials.notifications.reminder-form', [
                            'formType' => 'specific_treatment',
                            'action' => route('notifications.update', $reminder['id']),
                            'method' => 'PUT',
                            'reminder' => $reminder,
                            'dayLabels' => $dayLabels,
                            'hasPhone' => $data['has_phone'] ?? false,
                        ])
                    </div>
                    <div x-show="editingId !== {{ $reminder['id'] }}">
                        @include('partials.notifications.reminder-card', ['reminder' => $reminder])
                    </div>
                @empty
                    <x-portal.empty-state
                        class="!py-10"
                        :title="__('Sin tratamientos específicos')"
                        :description="__('Configura un recordatorio con fecha de fin para un tratamiento puntual.')"
                    >
                        <x-slot name="icon">
                            <flux:icon name="beaker" class="size-7 text-portal-primary dark:text-portal-primary" />
                        </x-slot>
                    </x-portal.empty-state>
                @endforelse
            </section>

            {{-- Appointments --}}
            <section x-show="tab === 'appointments'" x-cloak class="space-y-4">
                <div class="flex justify-end">
                    <flux:button variant="primary" icon="plus" class="portal-glass-button" type="button" @click="openCreate('appointment')">
                        {{ __('Nueva cita') }}
                    </flux:button>
                </div>

                <div x-show="createOpen && createType === 'appointment'" x-cloak>
                    @include('partials.notifications.reminder-form', [
                        'formType' => 'appointment',
                        'action' => route('notifications.store'),
                        'method' => 'POST',
                        'reminder' => null,
                        'dayLabels' => $dayLabels,
                        'hasPhone' => $data['has_phone'] ?? false,
                    ])
                </div>

                @forelse ($data['appointments'] as $reminder)
                    <div x-show="editingId === {{ $reminder['id'] }}" x-cloak>
                        @include('partials.notifications.reminder-form', [
                            'formType' => 'appointment',
                            'action' => route('notifications.update', $reminder['id']),
                            'method' => 'PUT',
                            'reminder' => $reminder,
                            'dayLabels' => $dayLabels,
                            'hasPhone' => $data['has_phone'] ?? false,
                        ])
                    </div>
                    <div x-show="editingId !== {{ $reminder['id'] }}">
                        @include('partials.notifications.reminder-card', ['reminder' => $reminder])
                    </div>
                @empty
                    <x-portal.empty-state
                        class="!py-10"
                        :title="__('Sin citas programadas')"
                        :description="__('Agrega la fecha de tu cita y elige con cuánta anticipación quieres el recordatorio.')"
                    >
                        <x-slot name="icon">
                            <flux:icon name="calendar-days" class="size-7 text-portal-primary dark:text-portal-primary" />
                        </x-slot>
                    </x-portal.empty-state>
                @endforelse
            </section>

            {{-- History --}}
            <section x-show="tab === 'history'" x-cloak class="space-y-3">
                @forelse ($data['history'] as $item)
                    <x-portal.card>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-portal-ink dark:text-zinc-100">{{ $item['title'] }}</p>
                                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                                    {{ $item['channel'] }} · {{ $item['scheduled_for'] }} · {{ $item['status'] }}
                                </p>
                                @if ($item['message'])
                                    <p class="mt-2 whitespace-pre-line text-sm text-portal-ink/90 dark:text-zinc-300">{{ $item['message'] }}</p>
                                @endif
                                @if ($item['error'])
                                    <p class="mt-2 text-sm text-portal-danger">{{ $item['error'] }}</p>
                                @endif
                            </div>
                        </div>
                    </x-portal.card>
                @empty
                    <x-portal.empty-state
                        class="!py-10"
                        :title="__('Sin historial todavía')"
                        :description="__('Cuando se disparen tus alarmas, verás aquí los avisos in-app y el resultado de WhatsApp.')"
                    >
                        <x-slot name="icon">
                            <flux:icon name="clock" class="size-7 text-portal-primary dark:text-portal-primary" />
                        </x-slot>
                    </x-portal.empty-state>
                @endforelse
            </section>
        @endif
    </div>
</x-layouts.app>
