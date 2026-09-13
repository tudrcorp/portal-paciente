<x-layouts.app.sidebar :title="__('Cerca de mí')">
    @php
        $categories = $geoConfig['categories'];
        $radii = $geoConfig['radii'];
        $defaultRadius = $geoConfig['defaultRadius'];
    @endphp

    <main
        class="portal-geo"
        data-geo-root
        data-geo-config="{{ json_encode($geoConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    >
        {{-- Lienzo del mapa. Leaflet lo puebla al montar; mientras tanto queda
             el degradado de fondo para que no haya un rectángulo blanco. --}}
        <div
            class="portal-geo__map"
            data-geo-map
            role="application"
            aria-label="{{ __('Mapa de centros de salud cercanos') }}"
        ></div>

        <div class="portal-geo__overlay">
            <button
                type="button"
                class="portal-geo__fab"
                data-geo-recenter
                aria-label="{{ __('Centrar en mi ubicación') }}"
                title="{{ __('Centrar en mi ubicación') }}"
            >
                <flux:icon name="map-pin" variant="mini" class="size-5" />
            </button>

            <button type="button" class="portal-geo__area" data-geo-search-area hidden>
                <flux:icon name="arrow-path" variant="micro" class="size-4" />
                <span>{{ __('Buscar en esta área') }}</span>
            </button>
        </div>

        <section
            class="portal-geo__sheet"
            data-geo-sheet
            data-snap="half"
            aria-label="{{ __('Buscador de centros de salud') }}"
        >
            {{-- Zona de arrastre del panel: asa + cabecera de ubicación.
                 Su alto define la posición «asomada» del panel. --}}
            <div class="portal-geo__grab" data-geo-origin-block>
                <button
                    type="button"
                    class="portal-geo__handle"
                    data-geo-handle
                    aria-label="{{ __('Arrastra para expandir o contraer el panel') }}"
                >
                    <span aria-hidden="true"></span>
                </button>

                <header class="portal-geo__origin">
                    <span class="portal-geo__origin-icon" aria-hidden="true">
                        <flux:icon name="map-pin" variant="mini" class="size-4" />
                    </span>

                    <div class="portal-geo__origin-copy">
                        <p class="portal-geo__origin-label">{{ __('Tu ubicación') }}</p>
                        <p class="portal-geo__origin-value" data-geo-address>{{ __('Obteniendo ubicación…') }}</p>
                    </div>

                    <span class="portal-geo__accuracy" data-geo-accuracy hidden></span>

                    {{-- Control explícito para replegar el panel y dejar el
                         mapa despejado. `data-geo-no-drag` lo excluye del
                         gesto de arrastre de la cabecera. --}}
                    <button
                        type="button"
                        class="portal-geo__collapse"
                        data-geo-collapse
                        data-geo-no-drag
                        aria-controls="portal-geo-panel"
                        aria-expanded="true"
                        aria-label="{{ __('Minimizar el panel') }}"
                        title="{{ __('Minimizar el panel') }}"
                    >
                        <flux:icon name="chevron-down" variant="mini" class="size-5" />
                    </button>
                </header>
            </div>

            <div class="portal-geo__panel" id="portal-geo-panel" data-geo-panel>
                <div data-geo-browse>
                    <div class="portal-geo__field">
                        <p class="portal-geo__field-label" id="portal-geo-categories-label">
                            {{ __('¿Qué necesitas?') }}
                        </p>

                        <div class="portal-geo__chips" role="group" aria-labelledby="portal-geo-categories-label">
                            <button
                                type="button"
                                class="portal-geo__chip portal-geo__chip--all is-active"
                                data-geo-category="all"
                                aria-pressed="true"
                            >
                                <span>{{ __('Todos') }}</span>
                            </button>

                            @foreach ($categories as $category)
                                <button
                                    type="button"
                                    class="portal-geo__chip portal-geo__chip--{{ $category['key'] }}"
                                    data-geo-category="{{ $category['key'] }}"
                                    aria-pressed="false"
                                >
                                    <span class="portal-geo__chip-dot" aria-hidden="true"></span>
                                    <span>{{ $category['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="portal-geo__field">
                        <p class="portal-geo__field-label" id="portal-geo-radius-label">
                            {{ __('Radio de búsqueda') }}
                        </p>

                        <div class="portal-geo__segmented" role="group" aria-labelledby="portal-geo-radius-label">
                            @foreach ($radii as $radius)
                                <button
                                    type="button"
                                    @class([
                                        'portal-geo__segment',
                                        'is-active' => $radius === $defaultRadius,
                                    ])
                                    data-geo-radius="{{ $radius }}"
                                    aria-pressed="{{ $radius === $defaultRadius ? 'true' : 'false' }}"
                                >
                                    {{ (int) ($radius / 1000) }} km
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <p class="portal-geo__count" data-geo-count aria-live="polite"></p>

                    <ul class="portal-geo__list" data-geo-list></ul>

                    <div class="portal-geo__state" data-geo-state></div>
                </div>

                <div class="portal-geo__detail" data-geo-detail hidden></div>
            </div>
        </section>
    </main>
</x-layouts.app.sidebar>
