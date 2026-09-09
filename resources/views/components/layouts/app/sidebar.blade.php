<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head')
    </head>
    <body class="portal-liquid-root min-h-screen bg-portal-canvas text-portal-ink antialiased">
        <flux:header sticky class="portal-top-nav">
            <a
                href="{{ route('dashboard') }}"
                class="me-4 flex shrink-0 items-center py-1 transition-opacity portal-transition-fast hover:opacity-90 sm:me-6"
                wire:navigate
            >
                <img
                    src="{{ asset('images/logoWhiteTDG.png') }}"
                    alt="{{ config('app.name', 'Laravel') }}"
                    width="200"
                    height="64"
                    class="h-8 w-auto max-w-[10rem] object-contain object-left sm:h-9 sm:max-w-[11rem]"
                />
            </a>

            <flux:spacer />

            <x-portal.nav-pills />

            <div class="portal-top-nav-actions ms-2 max-lg:hidden sm:ms-3">
                <div class="portal-top-nav-action">
                    <x-portal.theme-switcher inline />
                </div>

                <div class="portal-top-nav-action">
                    <flux:dropdown position="bottom" align="end" class="portal-top-nav-action-dropdown">
                        <flux:profile
                            :initials="auth()->user()->initials()"
                            circle
                            :chevron="false"
                            data-test="top-nav-menu-button"
                            class="portal-top-nav-profile cursor-pointer"
                        />

                <flux:menu class="portal-user-menu portal-user-menu-glass !min-w-0 !w-56 max-w-56">
                    <div class="portal-user-menu-user">
                        <span class="portal-user-menu-user__avatar" aria-hidden="true">
                            {{ auth()->user()->initials() }}
                        </span>
                        <div class="portal-user-menu-user__copy">
                            <p class="portal-user-menu-user__name">{{ auth()->user()->name }}</p>
                            <p class="portal-user-menu-user__email">{{ auth()->user()->email }}</p>
                        </div>
                    </div>

                    <flux:menu.separator class="portal-user-menu-separator portal-user-menu-separator--user" />

                    <flux:menu.radio.group>
                        <flux:menu.item class="portal-user-menu-item" :href="route('my-profile.show')" icon="user-circle" wire:navigate>{{ __('Mi Perfil') }}</flux:menu.item>
                        <flux:menu.item class="portal-user-menu-item" :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Ajustes') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator class="portal-user-menu-separator" />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="portal-user-menu-item portal-user-menu-item-danger w-full"
                            data-test="logout-button"
                        >
                            {{ __('Cerrar sesión') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
                    </flux:dropdown>
                </div>
            </div>

            <div class="portal-top-nav-mobile ms-2 lg:hidden">
                <x-portal.mobile-menu />
            </div>
        </flux:header>

        {{ $slot }}

        @fluxScripts

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const main = document.querySelector('.portal-main-inner');
                if (!main) return;

                const runEnter = () => {
                    main.classList.remove('is-entering');
                    void main.offsetWidth;
                    main.classList.add('is-entering');
                };

                const initGlassInteractive = () => {
                    const canTrackPointer = window.matchMedia('(pointer: fine)').matches;
                    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                    document.querySelectorAll('[data-glass-interactive]').forEach((el) => {
                        if (el.dataset.glassBound === 'true') return;
                        el.dataset.glassBound = 'true';

                        if (!canTrackPointer || reduceMotion) return;

                        el.addEventListener('pointermove', (event) => {
                            const rect = el.getBoundingClientRect();
                            const x = ((event.clientX - rect.left) / rect.width) * 100;
                            const y = ((event.clientY - rect.top) / rect.height) * 100;
                            el.style.setProperty('--glass-x', `${Math.max(0, Math.min(100, x))}%`);
                            el.style.setProperty('--glass-y', `${Math.max(0, Math.min(100, y))}%`);
                        });

                        el.addEventListener('pointerleave', () => {
                            el.style.setProperty('--glass-x', '50%');
                            el.style.setProperty('--glass-y', '50%');
                        });
                    });
                };

                main.addEventListener('animationend', (e) => {
                    if (e.animationName === 'portal-enter') {
                        main.classList.remove('is-entering');
                    }
                });

                runEnter();
                initGlassInteractive();
                document.addEventListener('livewire:navigated', runEnter);
                document.addEventListener('livewire:navigated', initGlassInteractive);

                document.addEventListener('livewire:navigated', () => {
                    const mode = window.localStorage.getItem('flux.appearance');
                    if (window.Flux && typeof window.Flux.applyAppearance === 'function') {
                        window.Flux.applyAppearance(mode || 'system');
                    }
                });

                window
                    .matchMedia('(prefers-color-scheme: dark)')
                    .addEventListener('change', () => {
                        if (!window.localStorage.getItem('flux.appearance') && window.Flux?.applyAppearance) {
                            window.Flux.applyAppearance('system');
                        }
                    });
            });
        </script>
    </body>
</html>
