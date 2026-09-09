<x-layouts.app :title="__('Inicio')">
    @php
        $displayName = trim((string) ($patientName ?? ''));
        $appointmentsPayload = collect($appointments ?? [])->values()->all();
        $appointmentDatesPayload = collect($appointmentDates ?? [])->values()->all();
    @endphp

    <div class="portal-dashboard portal-stack-appear portal-typography-fluid flex flex-1 flex-col gap-8">
        @if (session('portal.history_success'))
            <x-portal.alert variant="success" :title="__('Historia clínica lista')">
                {{ session('portal.history_success') }}
            </x-portal.alert>
        @endif

        @if (session('portal.appointment_whatsapp_success'))
            <x-portal.alert variant="success" :title="__('Orden enviada')">
                {{ session('portal.appointment_whatsapp_success') }}
            </x-portal.alert>
        @endif

        @if ($errors->any())
            <x-portal.alert variant="danger" :title="__('No se pudo enviar')">
                {{ $errors->first() }}
            </x-portal.alert>
        @endif

        <header
            class="portal-dashboard__hero motion-safe:animate-portal-fade"
            x-data="portalDashboardClock('es-VE')"
            x-init="start()"
        >
            <div class="portal-dashboard__greeting">
                <p class="portal-dashboard__hello">{{ __('Bienvenido') }}</p>
                @if ($displayName !== '')
                    <p class="portal-dashboard__name">{{ $displayName }}</p>
                @endif
                <p class="portal-dashboard__tagline">{{ __('Salud y Bienestar') }}</p>
            </div>

            <div class="portal-dashboard__clock" aria-live="polite">
                <p class="portal-dashboard__time">
                    <span x-text="time"></span>
                    <span class="portal-dashboard__meridiem" x-text="meridiem"></span>
                </p>
                <p class="portal-dashboard__date" x-text="date"></p>
            </div>
        </header>

        <div
            class="portal-dashboard__widgets"
            x-data="portalDashboardAgenda(@js($appointmentsPayload), @js($appointmentDatesPayload))"
        >
            {{-- 1. Accesos rápidos --}}
            <x-glass-panel class="portal-dash-widget portal-dash-widget--access">
                <div class="portal-dash-widget__header">
                    <h2 class="portal-dash-widget__title">{{ __('Accesos rápidos') }}</h2>
                    <p class="portal-dash-widget__subtitle">{{ __('Entra a las secciones principales del portal.') }}</p>
                </div>

                <div class="portal-dash-widget__body">
                    <div class="portal-dashboard__access-grid" role="list">
                        <div role="listitem">
                            <x-portal.dashboard-access-tile
                                :href="route('history')"
                                tone="history"
                                icon="document-text"
                                :title="__('Historia clínica')"
                                :hint="__('Historial médico')"
                            />
                        </div>
                        <div role="listitem">
                            <x-portal.dashboard-access-tile
                                :href="route('cases.index')"
                                tone="documents"
                                icon="folder-open"
                                :title="__('Documentos')"
                                :hint="__('Archivos y estudios')"
                            />
                        </div>
                        <div role="listitem">
                            <x-portal.dashboard-access-tile
                                :href="route('notifications.index')"
                                tone="notifications"
                                icon="bell"
                                :title="__('Notificaciones')"
                                :hint="__('Recordatorios')"
                            />
                        </div>
                        <div role="listitem">
                            <x-portal.dashboard-access-tile
                                :href="route('my-profile.show')"
                                tone="profile"
                                icon="user-circle"
                                :title="__('Mi perfil')"
                                :hint="__('Datos personales')"
                            />
                        </div>
                        <div role="listitem">
                            <x-portal.dashboard-access-tile
                                :href="route('help')"
                                tone="help"
                                icon="lifebuoy"
                                :title="__('Ayuda')"
                                :hint="__('Soporte y contacto')"
                            />
                        </div>
                    </div>
                </div>
            </x-glass-panel>

            {{-- 2. Calendario --}}
            <x-glass-panel class="portal-dash-widget portal-dash-widget--calendar">
                <div class="portal-dash-widget__header">
                    <h2 class="portal-dash-widget__title">{{ __('Calendario') }}</h2>
                </div>

                <div class="portal-dash-widget__body">
                    <div class="portal-calendar">
                        <div class="portal-calendar__nav">
                            <button type="button" class="portal-calendar__nav-btn" @click="prevMonth()" aria-label="{{ __('Mes anterior') }}">
                                <flux:icon name="chevron-left" class="size-4" />
                            </button>
                            <p class="portal-calendar__month" x-text="monthLabel"></p>
                            <button type="button" class="portal-calendar__nav-btn" @click="nextMonth()" aria-label="{{ __('Mes siguiente') }}">
                                <flux:icon name="chevron-right" class="size-4" />
                            </button>
                        </div>

                        <div class="portal-calendar__weekdays" aria-hidden="true">
                            <span>D</span><span>L</span><span>M</span><span>X</span><span>J</span><span>V</span><span>S</span>
                        </div>

                        <div class="portal-calendar__grid">
                            <template x-for="day in calendarDays" :key="day.key">
                                <button
                                    type="button"
                                    class="portal-calendar__day"
                                    :class="{
                                        'is-outside': day.outside,
                                        'is-today': day.isToday,
                                        'is-selected': day.key === selectedDate,
                                        'has-event': day.hasEvent,
                                    }"
                                    :aria-pressed="day.key === selectedDate"
                                    :aria-label="day.hasEvent
                                        ? `{{ __('Día') }} ${day.label}, {{ __('tiene cita agendada') }}`
                                        : `{{ __('Día') }} ${day.label}`"
                                    @click="selectDate(day.key)"
                                >
                                    <span class="portal-calendar__day-num" x-text="day.label"></span>
                                    <span class="portal-calendar__marker" x-show="day.hasEvent" aria-hidden="true">
                                        <span class="portal-calendar__marker-dot"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </x-glass-panel>

            {{-- 3. Citas del día --}}
            <x-glass-panel class="portal-dash-widget portal-dash-widget--schedule">
                <div class="portal-dash-widget__header">
                    <h2 class="portal-dash-widget__title">{{ __('Agenda del día') }}</h2>
                    <p class="portal-dash-widget__subtitle" x-text="selectedDateLabel"></p>
                </div>

                <div class="portal-dash-widget__body">
                    <div class="portal-schedule" x-show="selectedAppointments.length > 0">
                        <template x-for="item in selectedAppointments" :key="item.id">
                            <article class="portal-schedule__item" :data-tone="item.tone" :data-status="item.status || ''">
                                <div class="portal-schedule__time" aria-label="{{ __('Hora de la cita') }}">
                                    <span class="portal-schedule__hour" x-text="item.hour || item.start"></span>
                                    <span class="portal-schedule__meridiem" x-text="item.meridiem || ''"></span>
                                </div>

                                <div class="portal-schedule__body">
                                    <div class="portal-schedule__topline">
                                        <p class="portal-schedule__title">
                                            <span x-text="item.title"></span>
                                        </p>
                                        <span
                                            class="portal-schedule__badge"
                                            x-show="item.status_label"
                                            x-text="item.status_label"
                                        ></span>
                                    </div>

                                    <p class="portal-schedule__meta-line" x-show="item.supplier || item.case_code">
                                        <span x-show="item.supplier" x-text="item.supplier"></span>
                                        <span class="portal-schedule__meta-sep" x-show="item.supplier && item.case_code" aria-hidden="true">·</span>
                                        <span x-show="item.case_code">{{ __('Caso') }} <span x-text="item.case_code"></span></span>
                                    </p>

                                    <div class="portal-schedule__actions" x-show="item.supplier_whatsapp_url || item.service_order_whatsapp_url">
                                        <a
                                            class="portal-schedule__whatsapp"
                                            x-show="item.supplier_whatsapp_url"
                                            :href="item.supplier_whatsapp_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :aria-label="`{{ __('Contactar proveedor por WhatsApp') }} ${item.supplier_phone_display || ''}`"
                                        >
                                            <img
                                                src="{{ asset('icons/whatsapp.svg') }}"
                                                alt=""
                                                width="14"
                                                height="14"
                                                class="portal-schedule__whatsapp-icon"
                                            />
                                            <span x-text="item.supplier_phone_display || '{{ __('WhatsApp') }}'"></span>
                                        </a>

                                        <button
                                            type="button"
                                            class="portal-schedule__send-toggle"
                                            x-show="item.service_order_whatsapp_url"
                                            x-cloak
                                            @click="openSendOs(item)"
                                        >
                                            <img
                                                src="{{ asset('icons/whatsapp.svg') }}"
                                                alt=""
                                                width="14"
                                                height="14"
                                                class="portal-schedule__whatsapp-icon"
                                            />
                                            <span>{{ __('Enviar OS') }}</span>
                                        </button>
                                    </div>

                                    <p
                                        class="portal-schedule__subtitle"
                                        x-show="!item.supplier && !item.case_code && !item.supplier_whatsapp_url && !item.service_order_whatsapp_url"
                                        x-text="item.subtitle"
                                    ></p>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div class="portal-schedule__empty" x-show="selectedAppointments.length === 0" x-cloak>
                        <div class="portal-schedule__empty-icon" aria-hidden="true">
                            <flux:icon name="calendar-days" class="size-7" />
                        </div>
                        <p class="portal-schedule__empty-title">{{ __('Sin citas este día') }}</p>
                        <p class="portal-schedule__empty-copy">{{ __('Cuando Operaciones agende una cita, aparecerá aquí con fecha y hora.') }}</p>
                        <flux:button size="sm" variant="ghost" :href="route('notifications.index', ['tab' => 'appointments'])" wire:navigate>
                            {{ __('Gestionar recordatorios') }}
                        </flux:button>
                    </div>
                </div>
            </x-glass-panel>

            <template x-teleport="body">
                <div
                    class="portal-send-os"
                    x-show="sendOsOpen"
                    x-cloak
                    style="display: none;"
                    @keydown.escape.window="closeSendOs()"
                >
                    <div
                        class="portal-send-os__backdrop"
                        x-show="sendOsOpen"
                        x-transition.opacity.duration.200ms
                        @click="closeSendOs()"
                    ></div>

                    <div
                        class="portal-send-os__sheet"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="portal-send-os-title"
                        x-show="sendOsOpen"
                        x-transition:enter="portal-send-os-sheet-enter"
                        x-transition:enter-start="portal-send-os__sheet--enter"
                        x-transition:enter-end="portal-send-os__sheet--entered"
                        x-transition:leave="portal-send-os-sheet-leave"
                        x-transition:leave-start="portal-send-os__sheet--entered"
                        x-transition:leave-end="portal-send-os__sheet--enter"
                        @click.stop
                    >
                        <div class="portal-send-os__handle" aria-hidden="true"></div>

                        <header class="portal-send-os__header">
                            <div class="portal-send-os__heading">
                                <p class="portal-send-os__eyebrow">{{ __('Orden de servicio') }}</p>
                                <h3 id="portal-send-os-title" class="portal-send-os__title">
                                    {{ __('Enviar por WhatsApp') }}
                                </h3>
                            </div>
                            <button
                                type="button"
                                class="portal-send-os__close"
                                @click="closeSendOs()"
                                aria-label="{{ __('Cerrar') }}"
                            >
                                <flux:icon name="x-mark" class="size-5" />
                            </button>
                        </header>

                        <div class="portal-send-os__summary" x-show="sendOsItem">
                            <p class="portal-send-os__summary-title" x-text="sendOsItem?.title || ''"></p>
                            <p class="portal-send-os__summary-meta">
                                <span x-show="sendOsItem?.time" x-text="sendOsItem?.time"></span>
                                <span x-show="sendOsItem?.time && (sendOsItem?.supplier || sendOsItem?.case_code)"> · </span>
                                <span x-show="sendOsItem?.supplier" x-text="sendOsItem?.supplier"></span>
                                <span x-show="sendOsItem?.supplier && sendOsItem?.case_code"> · </span>
                                <span x-show="sendOsItem?.case_code">
                                    {{ __('Caso') }} <span x-text="sendOsItem?.case_code"></span>
                                </span>
                            </p>
                        </div>

                        <form
                            class="portal-send-os__form"
                            method="POST"
                            :action="sendOsItem?.service_order_whatsapp_url || '#'"
                        >
                            @csrf
                            <p class="portal-send-os__label">{{ __('¿A qué número la enviamos?') }}</p>

                            <label class="portal-send-os__option" :class="{ 'is-active': sendOsMode === 'profile' }">
                                <input type="radio" name="phone_mode" value="profile" x-model="sendOsMode" />
                                <span class="portal-send-os__option-body">
                                    <span class="portal-send-os__option-title">{{ __('Mi número') }}</span>
                                    <span
                                        class="portal-send-os__option-hint"
                                        x-text="sendOsItem?.patient_phone_display || '{{ __('No registrado en tu perfil') }}'"
                                    ></span>
                                </span>
                            </label>

                            <label class="portal-send-os__option" :class="{ 'is-active': sendOsMode === 'custom' }">
                                <input type="radio" name="phone_mode" value="custom" x-model="sendOsMode" />
                                <span class="portal-send-os__option-body">
                                    <span class="portal-send-os__option-title">{{ __('Otro número') }}</span>
                                    <span class="portal-send-os__option-hint">{{ __('Escribe el teléfono de destino') }}</span>
                                </span>
                            </label>

                            <div class="portal-send-os__custom" x-show="sendOsMode === 'custom'" x-cloak>
                                <label class="portal-send-os__field-label" for="portal-send-os-phone">
                                    {{ __('Número de WhatsApp') }}
                                </label>
                                <input
                                    id="portal-send-os-phone"
                                    type="tel"
                                    name="phone"
                                    class="portal-send-os__input"
                                    placeholder="0414..."
                                    inputmode="tel"
                                    autocomplete="tel"
                                    x-bind:required="sendOsMode === 'custom'"
                                />
                            </div>

                            <div class="portal-send-os__actions">
                                <button type="button" class="portal-send-os__cancel" @click="closeSendOs()">
                                    {{ __('Cancelar') }}
                                </button>
                                <button type="submit" class="portal-send-os__submit">
                                    <img
                                        src="{{ asset('icons/whatsapp.svg') }}"
                                        alt=""
                                        width="16"
                                        height="16"
                                    />
                                    <span>{{ __('Enviar ahora') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script>
        function portalDashboardClock(locale = 'es-VE') {
            return {
                time: '',
                meridiem: '',
                date: '',
                timer: null,

                start() {
                    this.tick();
                    this.timer = setInterval(() => this.tick(), 1000);
                },

                tick() {
                    const now = new Date();

                    const timeParts = new Intl.DateTimeFormat(locale, {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true,
                    }).formatToParts(now);

                    const hours = timeParts.find((part) => part.type === 'hour')?.value || '';
                    const minutes = timeParts.find((part) => part.type === 'minute')?.value || '';
                    this.time = `${hours}:${minutes}`;
                    this.meridiem = (timeParts.find((part) => part.type === 'dayPeriod')?.value || '').toUpperCase();

                    this.date = new Intl.DateTimeFormat(locale, {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                    }).format(now);
                },

                destroy() {
                    if (this.timer) {
                        clearInterval(this.timer);
                    }
                },
            };
        }

        function portalDashboardAgenda(appointments = [], appointmentDates = []) {
            const today = new Date();
            const todayKey = portalDateKey(today);

            return {
                appointments,
                appointmentDates: new Set(appointmentDates),
                viewYear: today.getFullYear(),
                viewMonth: today.getMonth(),
                selectedDate: todayKey,
                locale: 'es-VE',
                sendOsOpen: false,
                sendOsItem: null,
                sendOsMode: 'profile',

                get monthLabel() {
                    return new Intl.DateTimeFormat(this.locale, {
                        month: 'long',
                        year: 'numeric',
                    }).format(new Date(this.viewYear, this.viewMonth, 1));
                },

                get selectedDateLabel() {
                    const [year, month, day] = this.selectedDate.split('-').map(Number);
                    return new Intl.DateTimeFormat(this.locale, {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                    }).format(new Date(year, month - 1, day));
                },

                get selectedAppointments() {
                    return this.appointments
                        .filter((item) => item.date === this.selectedDate)
                        .sort((a, b) => a.start.localeCompare(b.start));
                },

                get calendarDays() {
                    const first = new Date(this.viewYear, this.viewMonth, 1);
                    const startOffset = first.getDay();
                    const start = new Date(this.viewYear, this.viewMonth, 1 - startOffset);
                    const days = [];

                    for (let i = 0; i < 42; i += 1) {
                        const date = new Date(start);
                        date.setDate(start.getDate() + i);
                        const key = portalDateKey(date);
                        const outside = date.getMonth() !== this.viewMonth;

                        days.push({
                            key,
                            label: date.getDate(),
                            outside,
                            isToday: key === todayKey,
                            hasEvent: this.appointmentDates.has(key),
                        });
                    }

                    return days;
                },

                prevMonth() {
                    if (this.viewMonth === 0) {
                        this.viewMonth = 11;
                        this.viewYear -= 1;
                    } else {
                        this.viewMonth -= 1;
                    }
                },

                nextMonth() {
                    if (this.viewMonth === 11) {
                        this.viewMonth = 0;
                        this.viewYear += 1;
                    } else {
                        this.viewMonth += 1;
                    }
                },

                selectDate(key) {
                    this.selectedDate = key;
                },

                openSendOs(item) {
                    this.sendOsItem = item;
                    this.sendOsMode = item?.patient_phone_display ? 'profile' : 'custom';
                    this.sendOsOpen = true;
                    document.documentElement.classList.add('portal-send-os-lock');
                },

                closeSendOs() {
                    this.sendOsOpen = false;
                    document.documentElement.classList.remove('portal-send-os-lock');
                    window.setTimeout(() => {
                        if (!this.sendOsOpen) {
                            this.sendOsItem = null;
                            this.sendOsMode = 'profile';
                        }
                    }, 220);
                },
            };
        }

        function portalDateKey(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    </script>
</x-layouts.app>
