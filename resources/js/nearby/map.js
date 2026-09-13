import L from 'leaflet';
import { pinMarkup, userMarkup } from './icons.js';

/**
 * Capa Leaflet de la pantalla. Concentra todo lo imperativo del mapa para que
 * el orquestador (index.js) trabaje solo con estado.
 */
export function createMapController(element, config) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const map = L.map(element, {
        zoomControl: false,
        attributionControl: true,
        // La pantalla ocupa el alto completo y no hace scroll de página, así
        // que el zoom con rueda no compite con el desplazamiento del documento.
        scrollWheelZoom: true,
        tap: true,
        preferCanvas: false,
        zoomSnap: 0.5,
        maxZoom: 19,
    });

    map.attributionControl.setPrefix('');

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    let tileLayer = null;
    let userMarker = null;
    let accuracyCircle = null;
    let radiusCircle = null;
    let routeCasing = null;
    let routeLine = null;

    const markers = new Map();
    const placesLayer = L.layerGroup().addTo(map);

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function applyTiles() {
        const source = isDark() ? config.tiles.dark : config.tiles.light;

        if (tileLayer) {
            map.removeLayer(tileLayer);
        }

        tileLayer = L.tileLayer(source.url, {
            attribution: source.attribution,
            subdomains: source.subdomains || 'abc',
            maxZoom: 19,
            // Mantener tiles fuera de pantalla evita el parpadeo blanco al
            // volver a centrar sobre el paciente.
            keepBuffer: 3,
            updateWhenIdle: false,
        }).addTo(map);

        tileLayer.getContainer()?.classList.add('portal-geo__tiles');
    }

    // El portal permite cambiar de tema en caliente; el mapa debe seguirlo.
    const themeObserver = new MutationObserver(() => applyTiles());
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    applyTiles();

    /** Relleno para que nada quede debajo del panel al encuadrar. */
    function framePadding() {
        const sheet = element.parentElement?.querySelector('[data-geo-sheet]');
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        const gutter = 32;

        if (!sheet) {
            return { topLeft: [gutter, gutter], bottomRight: [gutter, gutter] };
        }

        if (isDesktop) {
            return {
                topLeft: [sheet.offsetWidth + gutter, gutter],
                bottomRight: [gutter, gutter],
            };
        }

        const visible = Math.max(0, window.innerHeight - sheet.getBoundingClientRect().top);

        return {
            topLeft: [gutter, gutter + 56],
            bottomRight: [gutter, Math.min(visible + 16, window.innerHeight * 0.6)],
        };
    }

    function flyTo(latlng, zoom) {
        const padding = framePadding();
        const options = {
            paddingTopLeft: padding.topLeft,
            paddingBottomRight: padding.bottomRight,
            animate: !reduceMotion.matches,
        };

        map.flyToBounds(L.latLngBounds([latlng, latlng]), { ...options, maxZoom: zoom ?? 16 });
    }

    return {
        map,

        setUser(position) {
            const latlng = L.latLng(position.lat, position.lng);

            if (!userMarker) {
                userMarker = L.marker(latlng, {
                    icon: L.divIcon({
                        className: 'portal-geo-marker portal-geo-marker--user',
                        html: userMarkup(),
                        iconSize: [22, 22],
                        iconAnchor: [11, 11],
                    }),
                    interactive: false,
                    keyboard: false,
                    zIndexOffset: 1000,
                }).addTo(map);
            } else {
                userMarker.setLatLng(latlng);
            }

            if (Number.isFinite(position.accuracy) && position.accuracy > 25) {
                if (!accuracyCircle) {
                    accuracyCircle = L.circle(latlng, {
                        radius: position.accuracy,
                        className: 'portal-geo-accuracy',
                        interactive: false,
                    }).addTo(map);
                } else {
                    accuracyCircle.setLatLng(latlng).setRadius(position.accuracy);
                }
            } else if (accuracyCircle) {
                map.removeLayer(accuracyCircle);
                accuracyCircle = null;
            }
        },

        setSearchArea(center, radius) {
            const latlng = L.latLng(center.lat, center.lng);

            if (!radiusCircle) {
                radiusCircle = L.circle(latlng, {
                    radius,
                    className: 'portal-geo-radius',
                    interactive: false,
                }).addTo(map);

                radiusCircle.bringToBack();

                return;
            }

            radiusCircle.setLatLng(latlng).setRadius(radius);
        },

        setPlaces(places, onSelect) {
            placesLayer.clearLayers();
            markers.clear();

            places.forEach((place) => {
                const marker = L.marker([place.lat, place.lng], {
                    icon: L.divIcon({
                        className: 'portal-geo-marker',
                        html: pinMarkup(place.category),
                        iconSize: [32, 40],
                        iconAnchor: [16, 38],
                    }),
                    keyboard: true,
                    title: place.name,
                    alt: place.name,
                    riseOnHover: true,
                });

                marker.on('click', () => onSelect(place));
                marker.addTo(placesLayer);
                markers.set(place.id, marker);
            });
        },

        highlight(placeId) {
            markers.forEach((marker, id) => {
                const node = marker.getElement();

                if (!node) {
                    return;
                }

                node.classList.toggle('is-selected', id === placeId);
                marker.setZIndexOffset(id === placeId ? 900 : 0);
            });
        },

        focusPlace(place) {
            flyTo([place.lat, place.lng], 16);
        },

        centerOnUser(position, zoom = 15) {
            flyTo([position.lat, position.lng], zoom);
        },

        drawRoute(geometry) {
            this.clearRoute();

            if (!geometry?.length) {
                return;
            }

            // Dos trazos superpuestos: el inferior da contraste sobre cualquier
            // fondo del mapa, el superior es el color de marca.
            routeCasing = L.polyline(geometry, {
                className: 'portal-geo-route portal-geo-route--casing',
                interactive: false,
                smoothFactor: 1.2,
            }).addTo(map);

            routeLine = L.polyline(geometry, {
                className: 'portal-geo-route portal-geo-route--line',
                interactive: false,
                smoothFactor: 1.2,
            }).addTo(map);

            const padding = framePadding();

            map.flyToBounds(routeLine.getBounds(), {
                paddingTopLeft: padding.topLeft,
                paddingBottomRight: padding.bottomRight,
                animate: !reduceMotion.matches,
                maxZoom: 17,
            });
        },

        clearRoute() {
            [routeCasing, routeLine].forEach((layer) => {
                if (layer) {
                    map.removeLayer(layer);
                }
            });

            routeCasing = null;
            routeLine = null;
        },

        getCenter() {
            const center = map.getCenter();

            return { lat: center.lat, lng: center.lng };
        },

        onMoveEnd(handler) {
            map.on('moveend', handler);

            return () => map.off('moveend', handler);
        },

        invalidate() {
            map.invalidateSize({ animate: false });
        },

        setView(center, zoom) {
            map.setView([center.lat, center.lng], zoom, { animate: false });
        },

        destroy() {
            themeObserver.disconnect();
            markers.clear();
            map.remove();
        },
    };
}
