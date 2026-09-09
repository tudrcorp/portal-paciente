@php
    $items = [
        [
            'label' => __('Historia clínica'),
            'subtitle' => __('Historial y consultas'),
            'href' => route('history'),
            'current' => request()->routeIs('history'),
            'icon' => 'document-text',
            'tone' => 'history',
        ],
        [
            'label' => __('Documentos'),
            'subtitle' => __('Archivos clínicos'),
            'href' => route('cases.index'),
            'current' => request()->routeIs('cases.*'),
            'icon' => 'folder-open',
            'tone' => 'documents',
        ],
        [
            'label' => __('Notificaciones'),
            'subtitle' => __('Recordatorios y avisos'),
            'href' => route('notifications.index'),
            'current' => request()->routeIs('notifications.*'),
            'icon' => 'bell',
            'tone' => 'notifications',
        ],
        [
            'label' => __('Ayuda'),
            'subtitle' => __('Soporte y contacto'),
            'href' => route('help'),
            'current' => request()->routeIs('help'),
            'icon' => 'whatsapp',
            'tone' => 'help',
        ],
    ];
@endphp

<nav class="portal-nav-pills max-lg:hidden" aria-label="{{ __('Menú principal') }}">
    <div class="portal-nav-pills__track">
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                wire:navigate
                @class([
                    'portal-nav-pills__item',
                    'portal-nav-pills__item--'.$item['tone'],
                    'is-current' => $item['current'],
                ])
                @if ($item['current'])
                    aria-current="page"
                @endif
                @if (($item['icon'] ?? '') === 'whatsapp')
                    data-test="portal-help-link"
                @endif
            >
                <span class="portal-nav-pills__icon" aria-hidden="true">
                    @if (($item['icon'] ?? '') === 'whatsapp')
                        <img
                            src="{{ asset('icons/whatsapp.svg') }}"
                            alt=""
                            width="16"
                            height="16"
                            class="portal-nav-pills__whatsapp-icon size-4"
                        />
                    @else
                        <flux:icon :name="$item['icon']" variant="mini" class="size-4" />
                    @endif
                </span>
                <span class="portal-nav-pills__copy">
                    <span class="portal-nav-pills__label">{{ $item['label'] }}</span>
                    <span class="portal-nav-pills__subtitle">{{ $item['subtitle'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</nav>
