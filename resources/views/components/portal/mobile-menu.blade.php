@php
    $user = auth()->user();
    $initials = method_exists($user, 'initials') ? $user->initials() : strtoupper(mb_substr((string) $user->name, 0, 2));
@endphp

<div
    class="portal-mobile-menu-root"
    x-data="{
        isOpen: false,
        show() {
            this.isOpen = true;
            document.documentElement.classList.add('portal-mobile-sheet-lock');
        },
        hide() {
            this.isOpen = false;
            document.documentElement.classList.remove('portal-mobile-sheet-lock');
        },
        init() {
            this._onNavigated = () => this.hide();
            document.addEventListener('livewire:navigated', this._onNavigated);
        },
        destroy() {
            document.removeEventListener('livewire:navigated', this._onNavigated);
            document.documentElement.classList.remove('portal-mobile-sheet-lock');
        },
    }"
    @keydown.escape.window="hide()"
>
    <button
        type="button"
        class="portal-mobile-menu-trigger"
        aria-label="{{ __('Abrir menú') }}"
        aria-haspopup="dialog"
        :aria-expanded="isOpen.toString()"
        data-test="portal-mobile-menu-trigger"
        @click="show()"
    >
        <flux:icon name="ellipsis-vertical" variant="mini" class="size-5" />
    </button>

    <template x-teleport="body">
        <div
            class="portal-mobile-sheet"
            x-show="isOpen"
            x-cloak
            style="display: none;"
        >
            <div
                class="portal-mobile-sheet__backdrop"
                x-show="isOpen"
                x-transition.opacity.duration.200ms
                @click="hide()"
            ></div>

            <nav
                class="portal-mobile-sheet__panel portal-mobile-sheet__panel--glass"
                role="dialog"
                aria-modal="true"
                aria-labelledby="portal-mobile-sheet-title"
                x-show="isOpen"
                x-transition:enter="portal-mobile-sheet-enter"
                x-transition:enter-start="portal-mobile-sheet__panel--enter"
                x-transition:enter-end="portal-mobile-sheet__panel--entered"
                x-transition:leave="portal-mobile-sheet-leave"
                x-transition:leave-start="portal-mobile-sheet__panel--entered"
                x-transition:leave-end="portal-mobile-sheet__panel--enter"
                @click.stop
            >
                <div class="portal-mobile-sheet__handle" aria-hidden="true"></div>

                <header class="portal-mobile-sheet__header">
                    <div class="portal-mobile-menu-user">
                        <span class="portal-mobile-menu-user__avatar" aria-hidden="true">
                            {{ $initials }}
                        </span>
                        <div class="portal-mobile-menu-user__copy">
                            <p id="portal-mobile-sheet-title" class="portal-mobile-menu-user__name">
                                {{ $user->name }}
                            </p>
                            <p class="portal-mobile-menu-user__email">{{ $user->email }}</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="portal-mobile-sheet__close"
                        @click="hide()"
                        aria-label="{{ __('Cerrar menú') }}"
                    >
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </header>

                <div class="portal-mobile-sheet__section" role="group" aria-label="{{ __('Navegación principal') }}">
                    <a
                        @class([
                            'portal-mobile-sheet__item',
                            'is-current' => request()->routeIs('history'),
                        ])
                        href="{{ route('history') }}"
                        wire:navigate
                        @click="hide()"
                    >
                        <flux:icon name="document-text" class="size-5 shrink-0" />
                        <span>{{ __('Historia clínica') }}</span>
                    </a>

                    <a
                        @class([
                            'portal-mobile-sheet__item',
                            'is-current' => request()->routeIs('cases.*'),
                        ])
                        href="{{ route('cases.index') }}"
                        wire:navigate
                        @click="hide()"
                    >
                        <flux:icon name="folder-open" class="size-5 shrink-0" />
                        <span>{{ __('Documentos') }}</span>
                    </a>

                    <a
                        @class([
                            'portal-mobile-sheet__item',
                            'is-current' => request()->routeIs('notifications.*'),
                        ])
                        href="{{ route('notifications.index') }}"
                        wire:navigate
                        @click="hide()"
                    >
                        <flux:icon name="bell" class="size-5 shrink-0" />
                        <span>{{ __('Notificaciones') }}</span>
                    </a>

                    <a
                        @class([
                            'portal-mobile-sheet__item',
                            'is-current' => request()->routeIs('help'),
                        ])
                        href="{{ route('help') }}"
                        wire:navigate
                        @click="hide()"
                    >
                        <img
                            src="{{ asset('icons/whatsapp.svg') }}"
                            alt=""
                            width="20"
                            height="20"
                            class="size-5 shrink-0"
                        />
                        <span>{{ __('Ayuda') }}</span>
                    </a>
                </div>

                <div class="portal-mobile-sheet__divider" role="separator"></div>

                <div class="portal-mobile-sheet__section" role="group" aria-label="{{ __('Cuenta') }}">
                    <a
                        @class([
                            'portal-mobile-sheet__item',
                            'is-current' => request()->routeIs('my-profile.*'),
                        ])
                        href="{{ route('my-profile.show') }}"
                        wire:navigate
                        @click="hide()"
                    >
                        <flux:icon name="user-circle" class="size-5 shrink-0" />
                        <span>{{ __('Mi Perfil') }}</span>
                    </a>

                    <a
                        @class([
                            'portal-mobile-sheet__item',
                            'is-current' => request()->routeIs('profile.*', 'password.*', 'appearance.*', 'two-factor.*'),
                        ])
                        href="{{ route('profile.edit') }}"
                        wire:navigate
                        @click="hide()"
                    >
                        <flux:icon name="cog" class="size-5 shrink-0" />
                        <span>{{ __('Ajustes') }}</span>
                    </a>
                </div>

                <div class="portal-mobile-sheet__divider" role="separator"></div>

                <form method="POST" action="{{ route('logout') }}" class="portal-mobile-sheet__logout">
                    @csrf
                    <button
                        type="submit"
                        class="portal-mobile-sheet__item portal-mobile-sheet__item--danger"
                        data-test="logout-button"
                    >
                        <flux:icon name="arrow-right-start-on-rectangle" class="size-5 shrink-0" />
                        <span>{{ __('Cerrar sesión') }}</span>
                    </button>
                </form>
            </nav>
        </div>
    </template>
</div>
