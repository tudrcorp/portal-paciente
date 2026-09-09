<x-layouts.app :title="__('Mi perfil')">
    <div class="portal-stack-appear portal-typography-fluid mx-auto flex w-full max-w-[1220px] flex-col gap-6 motion-safe:animate-portal-fade xl:gap-8">
        <header class="flex flex-col gap-3 border-b border-portal-border-soft/70 pb-5 dark:border-white/10">
            <div class="flex items-start gap-4">
                @if ($isPatient)
                    <flux:avatar
                        :name="$overview['name']"
                        color="sky"
                        size="lg"
                        circle
                        class="mt-0.5 shrink-0 shadow-lg shadow-black/10"
                    />
                @else
                    <span
                        class="mt-0.5 flex size-11 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/[0.08] text-portal-primary shadow-lg shadow-black/10 backdrop-blur-md dark:bg-white/[0.06] dark:text-portal-primary"
                        aria-hidden="true"
                    >
                        <flux:icon.user-circle class="size-6" />
                    </span>
                @endif
                <div class="min-w-0">
                    <flux:heading size="xl" class="font-semibold tracking-tight text-portal-ink dark:text-zinc-100">
                        {{ __('Mi Perfil') }}
                    </flux:heading>
                    <flux:subheading class="mt-1 max-w-3xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                        {{ __('Consulta aquí tus datos personales y el estado de tu afiliación en el sistema.') }}
                    </flux:subheading>
                </div>
            </div>
        </header>

        @if (! $isPatient)
            <x-portal.alert variant="warning" :title="__('Cuenta no vinculada a paciente')">
                {{ __('Esta sección está disponible únicamente para pacientes autenticados.') }}
            </x-portal.alert>
        @else
            <x-glass-panel variant="ambient">
                <div class="flex flex-col gap-4 sm:gap-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-portal-muted dark:text-zinc-400">{{ __('Paciente') }}</p>
                            <h2 class="mt-1 text-2xl font-semibold tracking-tight text-portal-ink dark:text-zinc-100">{{ $overview['name'] }}</h2>
                            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">{{ __('Documento:') }} <span class="font-medium text-portal-ink dark:text-zinc-200">{{ $overview['document'] }}</span></p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge color="sky" size="sm">{{ __('Paciente') }}</flux:badge>
                            <flux:badge color="sky" size="sm">{{ __($overview['affiliationBadge']) }}</flux:badge>
                        </div>
                    </div>

                    <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        <article class="portal-overview-chip flex items-start gap-3">
                            <span class="portal-field-icon" aria-hidden="true"><flux:icon.envelope class="size-4" /></span>
                            <span class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-portal-muted dark:text-zinc-400">{{ __('Correo electrónico') }}</p>
                                @if ($overview['email'] !== 'No disponible')
                                    <a href="mailto:{{ $overview['email'] }}" class="mt-1 block truncate text-sm font-medium text-portal-ink portal-infolist-link dark:text-zinc-100">
                                        {{ $overview['email'] }}
                                    </a>
                                @else
                                    <p class="mt-1 text-sm italic text-portal-muted/80 dark:text-zinc-500">{{ $overview['email'] }}</p>
                                @endif
                            </span>
                        </article>

                        <article class="portal-overview-chip flex items-start gap-3">
                            <span class="portal-field-icon" aria-hidden="true"><flux:icon.phone class="size-4" /></span>
                            <span class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-portal-muted dark:text-zinc-400">{{ __('Teléfono') }}</p>
                                @if ($overview['phone'] !== 'No disponible')
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $overview['phone']) }}" class="mt-1 block truncate text-sm font-medium text-portal-ink portal-infolist-link dark:text-zinc-100">
                                        {{ $overview['phone'] }}
                                    </a>
                                @else
                                    <p class="mt-1 text-sm italic text-portal-muted/80 dark:text-zinc-500">{{ $overview['phone'] }}</p>
                                @endif
                            </span>
                        </article>

                        <article class="portal-overview-chip flex items-start gap-3 sm:col-span-2 lg:col-span-1">
                            <span class="portal-field-icon" aria-hidden="true"><flux:icon.briefcase class="size-4" /></span>
                            <span class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-portal-muted dark:text-zinc-400">{{ __('Tipo de afiliación') }}</p>
                                <p class="mt-1 truncate text-sm font-medium text-portal-ink dark:text-zinc-100">{{ __($overview['affiliationTitle']) }}</p>
                            </span>
                        </article>
                    </div>
                </div>
            </x-glass-panel>

            <nav
                x-data="{
                    active: 'datos-principales',
                    observer: null,
                    segReady: false,
                    setIndicator() {
                        const id = this.active;
                        const link = this.$root.querySelector(`a[href='#${id}']`);
                        if (!link) return;

                        this.$root.style.setProperty('--seg-x', `${link.offsetLeft}px`);
                        this.$root.style.setProperty('--seg-w', `${link.offsetWidth}px`);
                        this.segReady = true;
                    },
                    activate(id) {
                        this.active = id;
                        this.$nextTick(() => this.setIndicator());
                    },
                    init() {
                        this.observer = new IntersectionObserver(
                            (entries) => {
                                entries.forEach((entry) => {
                                    if (entry.isIntersecting) {
                                        this.activate(entry.target.id);
                                    }
                                });
                            },
                            { rootMargin: '-45% 0px -50% 0px', threshold: 0 }
                        );

                        this.$root.querySelectorAll('a[href^=\'#\']').forEach((link) => {
                            const section = document.querySelector(link.getAttribute('href'));
                            if (section) this.observer.observe(section);
                        });

                        this.$nextTick(() => this.setIndicator());
                        window.addEventListener('resize', () => this.setIndicator());
                    },
                }"
                class="portal-segmented-nav portal-segmented-nav-enhanced -mx-1"
                :data-seg-ready="segReady ? 'true' : 'false'"
                aria-label="{{ __('Navegación de perfil') }}"
            >
                <div class="portal-segmented-track">
                    <span class="portal-segmented-indicator" aria-hidden="true"></span>
                    <a
                        href="#datos-principales"
                        :class="active === 'datos-principales' ? 'portal-segmented-link-active' : ''"
                        class="portal-segmented-link"
                        @click="activate('datos-principales')"
                    >
                        <flux:icon.user-circle class="size-4" />
                        {{ __('Datos principales') }}
                    </a>
                    <a
                        href="#datos-afiliacion"
                        :class="active === 'datos-afiliacion' ? 'portal-segmented-link-active' : ''"
                        class="portal-segmented-link"
                        @click="activate('datos-afiliacion')"
                    >
                        <flux:icon.shield-check class="size-4" />
                        {{ __('Afiliación') }}
                    </a>
                    @if ($affiliation && $affiliation['member'])
                        <a
                            href="#datos-empresa"
                            :class="active === 'datos-empresa' ? 'portal-segmented-link-active' : ''"
                            class="portal-segmented-link"
                            @click="activate('datos-empresa')"
                        >
                            <flux:icon.building-office class="size-4" />
                            {{ __('Afiliado en empresa') }}
                        </a>
                    @endif
                </div>
            </nav>

            <x-glass-panel id="datos-principales" class="scroll-mt-20">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Información principal') }}</h2>
                </div>
                <div class="portal-liquid-section">
                    <div class="portal-infolist">
                        @foreach ($patient as $line)
                            <article class="portal-infolist-row">
                                <div class="portal-infolist-heading">
                                    <span class="portal-field-icon" aria-hidden="true">
                                        <flux:icon :icon="$line['icon']" class="size-4" />
                                    </span>
                                    <p class="portal-infolist-label dark:text-zinc-400">{{ __($line['label']) }}</p>
                                </div>
                                @if ($line['isMissing'])
                                    <p class="portal-infolist-value italic text-portal-muted/80 dark:text-zinc-500">{{ __($line['value']) }}</p>
                                @elseif ($line['type'] === 'email')
                                    <a href="mailto:{{ $line['value'] }}" class="portal-infolist-value portal-infolist-link dark:text-zinc-100">{{ $line['value'] }}</a>
                                @elseif ($line['type'] === 'phone')
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $line['value']) }}" class="portal-infolist-value portal-infolist-link dark:text-zinc-100">{{ $line['value'] }}</a>
                                @else
                                    <p class="portal-infolist-value dark:text-zinc-100">{{ $line['value'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </x-glass-panel>

            <x-glass-panel id="datos-afiliacion" class="scroll-mt-20">
                @if ($affiliation)
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __($affiliation['title']) }}</h2>
                        <flux:badge color="sky" size="sm">{{ __($affiliation['type']) }}</flux:badge>
                    </div>

                    <div class="portal-liquid-section">
                        <div class="portal-infolist">
                            @foreach ($affiliation['data'] as $line)
                                <article class="portal-infolist-row">
                                    <div class="portal-infolist-heading">
                                        <span class="portal-field-icon" aria-hidden="true">
                                            <flux:icon :icon="$line['icon']" class="size-4" />
                                        </span>
                                        <p class="portal-infolist-label dark:text-zinc-400">{{ __($line['label']) }}</p>
                                    </div>
                                    @if ($line['isMissing'])
                                        <p class="portal-infolist-value italic text-portal-muted/80 dark:text-zinc-500">{{ __($line['value']) }}</p>
                                    @elseif ($line['type'] === 'email')
                                        <a href="mailto:{{ $line['value'] }}" class="portal-infolist-value portal-infolist-link dark:text-zinc-100">{{ $line['value'] }}</a>
                                    @elseif ($line['type'] === 'phone')
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $line['value']) }}" class="portal-infolist-value portal-infolist-link dark:text-zinc-100">{{ $line['value'] }}</a>
                                    @else
                                        <p class="portal-infolist-value dark:text-zinc-100">{{ $line['value'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>

                    @if ($affiliation['member'])
                        <div id="datos-empresa" class="mt-5 scroll-mt-20">
                            <h3 class="mb-3 text-sm font-semibold text-portal-ink dark:text-zinc-100">{{ __('Datos del afiliado en empresa') }}</h3>
                            <div class="portal-liquid-section">
                                <div class="portal-infolist">
                                    @foreach ($affiliation['member'] as $line)
                                        <article class="portal-infolist-row">
                                            <div class="portal-infolist-heading">
                                                <span class="portal-field-icon" aria-hidden="true">
                                                    <flux:icon :icon="$line['icon']" class="size-4" />
                                                </span>
                                                <p class="portal-infolist-label dark:text-zinc-400">{{ __($line['label']) }}</p>
                                            </div>
                                            @if ($line['isMissing'])
                                                <p class="portal-infolist-value italic text-portal-muted/80 dark:text-zinc-500">{{ __($line['value']) }}</p>
                                            @else
                                                <p class="portal-infolist-value dark:text-zinc-100">{{ $line['value'] }}</p>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="portal-liquid-section">
                        <h3 class="text-sm font-semibold text-portal-ink dark:text-zinc-100">{{ __('Paciente externo') }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-portal-muted dark:text-zinc-400">
                            {{ __('No se encontró una afiliación activa individual o corporativa para tu cuenta. Solo se muestra tu información principal.') }}
                        </p>
                    </div>
                @endif
            </x-glass-panel>
        @endif
    </div>
</x-layouts.app>
