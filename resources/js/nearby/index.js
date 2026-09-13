import 'leaflet/dist/leaflet.css';
import '../../css/nearby.css';

import { createGeoApi, isAbort } from './api.js';
import { createMapController } from './map.js';
import { createSheet } from './sheet.js';
import { categoryColor, categorySvg } from './icons.js';
import { escapeHtml, formatAccuracy, formatArrival, formatDistance, formatDuration } from './format.js';

const GEO_OPTIONS = {
    // Alta precisión: el objetivo es distinguir una cuadra de otra, no el país.
    enableHighAccuracy: true,
    timeout: 15000,
    maximumAge: 0,
};

export function mountNearby(root) {
    const config = JSON.parse(root.dataset.geoConfig);

    const el = {
        map: root.querySelector('[data-geo-map]'),
        sheet: root.querySelector('[data-geo-sheet]'),
        handle: root.querySelector('[data-geo-handle]'),
        originBlock: root.querySelector('[data-geo-origin-block]'),
        address: root.querySelector('[data-geo-address]'),
        accuracy: root.querySelector('[data-geo-accuracy]'),
        panel: root.querySelector('[data-geo-panel]'),
        browse: root.querySelector('[data-geo-browse]'),
        detail: root.querySelector('[data-geo-detail]'),
        list: root.querySelector('[data-geo-list]'),
        count: root.querySelector('[data-geo-count]'),
        state: root.querySelector('[data-geo-state]'),
        recenter: root.querySelector('[data-geo-recenter]'),
        searchArea: root.querySelector('[data-geo-search-area]'),
        collapse: root.querySelector('[data-geo-collapse]'),
        chips: Array.from(root.querySelectorAll('[data-geo-category]')),
        radii: Array.from(root.querySelectorAll('[data-geo-radius]')),
    };

    const categoryLabels = new Map(config.categories.map((item) => [item.key, item.label]));
    const allCategories = config.categories.map((item) => item.key);

    const state = {
        user: null,
        searchCenter: null,
        categories: new Set(),
        radius: config.defaultRadius,
        profile: 'driving',
        places: [],
        selected: null,
        route: null,
        routeStatus: 'idle',
        status: 'locating',
        located: false,
    };

    const api = createGeoApi(config.endpoints);
    const map = createMapController(el.map, config);

    // El asa está dentro del bloque de arrastre, así que basta con escuchar
    // en el contenedor: registrar ambos duplicaría la captura del puntero.
    const sheet = createSheet(el.sheet, {
        handles: [el.originBlock].filter(Boolean),
        // Arrastrar hasta abajo equivale a pulsar «minimizar»: el botón debe
        // reflejarlo para no contradecir lo que el paciente ve.
        onSnap: (snap) => setCollapsedClass(snap === 'peek'),
    });

    let watchId = null;
    let searchTimer = null;
    let destroyed = false;

    /* ------------------------------------------------------------- Plegado */

    function setCollapsedClass(collapsed) {
        el.sheet.classList.toggle('is-collapsed', collapsed);
        el.collapse.setAttribute('aria-expanded', String(!collapsed));

        const label = collapsed ? 'Expandir el panel' : 'Minimizar el panel';
        el.collapse.setAttribute('aria-label', label);
        el.collapse.title = label;
    }

    function isCollapsed() {
        return el.sheet.classList.contains('is-collapsed');
    }

    // El plegado se expresa distinto a cada lado del breakpoint (posición del
    // panel en móvil, clase en escritorio): al cruzarlo se parte de expandido
    // para que el botón y lo que se ve nunca se contradigan.
    const desktopQuery = window.matchMedia('(min-width: 1024px)');

    function onBreakpointChange() {
        setCollapsedClass(false);
        sheet.snapTo('half', { animated: false, silent: true });
    }

    function toggleCollapsed() {
        const collapsed = !isCollapsed();

        setCollapsedClass(collapsed);

        // En móvil el plegado es una posición más del panel arrastrable; en
        // escritorio basta con ocultar el cuerpo de la columna.
        if (!sheet.isDesktop()) {
            sheet.snapTo(collapsed ? 'peek' : 'half', { silent: true });
        }
    }

    /* ---------------------------------------------------------------- Altura */

    // La barra superior es sticky y su alto cambia entre breakpoints: medirla
    // es más fiable que fijar un valor y que el mapa quede recortado.
    function syncTopOffset() {
        const header = document.querySelector('.portal-top-nav');
        root.style.setProperty('--portal-geo-top', `${Math.round(header?.offsetHeight ?? 60)}px`);
    }

    /* ------------------------------------------------------------- Ubicación */

    function activeCategories() {
        return state.categories.size === 0 ? allCategories : Array.from(state.categories);
    }

    function distanceBetween(a, b) {
        const toRad = (value) => (value * Math.PI) / 180;
        const radius = 6371000;
        const deltaLat = toRad(b.lat - a.lat);
        const deltaLng = toRad(b.lng - a.lng);

        const h = Math.sin(deltaLat / 2) ** 2
            + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(deltaLng / 2) ** 2;

        return radius * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    }

    function locate() {
        if (!navigator.geolocation) {
            state.status = 'unsupported';
            renderState();

            return;
        }

        state.status = 'locating';
        renderState();

        navigator.geolocation.getCurrentPosition(onPosition, onPositionError, GEO_OPTIONS);
    }

    function watch() {
        if (watchId !== null || !navigator.geolocation) {
            return;
        }

        // El primer fix suele venir de la red y es impreciso; el seguimiento
        // lo refina con GPS sin volver a molestar al paciente.
        watchId = navigator.geolocation.watchPosition(
            (position) => onPosition(position, { refine: true }),
            () => {},
            { ...GEO_OPTIONS, maximumAge: 5000, timeout: 25000 }
        );
    }

    function onPosition(position, { refine = false } = {}) {
        if (destroyed) {
            return;
        }

        const next = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: position.coords.accuracy,
        };

        const previous = state.user;
        state.user = next;
        map.setUser(next);
        renderAccuracy();

        if (!state.located) {
            state.located = true;
            state.status = 'ready';
            state.searchCenter = next;

            map.setView(next, 15);
            map.centerOnUser(next, 15);
            resolveAddress(next);
            runSearch();
            watch();

            return;
        }

        if (!refine || !previous) {
            return;
        }

        // Solo se rehace el trabajo caro si el paciente se movió de verdad.
        if (distanceBetween(previous, next) > 150) {
            resolveAddress(next);

            if (!state.selected) {
                state.searchCenter = next;
                runSearch();
            }
        }
    }

    function onPositionError(error) {
        if (destroyed) {
            return;
        }

        state.status = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable';

        // Sin GPS el mapa sigue siendo útil: se encuadra la ciudad para que el
        // paciente pueda buscar moviendo el mapa a mano.
        if (!state.located) {
            map.setView(config.fallbackCenter, 12);
            el.address.textContent = 'Ubicación no disponible';
        }

        renderState();
    }

    async function resolveAddress(position) {
        el.address.textContent = 'Ubicando…';

        try {
            const { address } = await api.address(position);
            el.address.textContent = address || 'Tu ubicación actual';
        } catch (error) {
            if (!isAbort(error)) {
                el.address.textContent = 'Tu ubicación actual';
            }
        }
    }

    /* --------------------------------------------------------------- Búsqueda */

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(runSearch, 280);
    }

    async function runSearch() {
        const center = state.searchCenter ?? state.user;

        if (!center) {
            return;
        }

        state.status = 'searching';
        renderState();
        map.setSearchArea(center, state.radius);
        updateSearchAreaButton();

        try {
            const { places } = await api.places({
                lat: center.lat,
                lng: center.lng,
                radius: state.radius,
                categories: activeCategories(),
            });

            if (destroyed) {
                return;
            }

            state.places = places;
            state.status = places.length ? 'ready' : 'empty';

            map.setPlaces(places, (place) => selectPlace(place));
            renderList();
            renderState();
        } catch (error) {
            if (isAbort(error) || destroyed) {
                return;
            }

            state.status = 'error';
            state.errorMessage = error.message;
            state.places = [];
            map.setPlaces([], () => {});
            renderList();
            renderState();
        }
    }

    /* ------------------------------------------------------------------ Ruta */

    function selectPlace(place) {
        state.selected = place;
        state.route = null;
        state.routeStatus = 'loading';

        map.highlight(place.id);
        renderDetail();
        showDetail();
        requestRoute();
    }

    function clearSelection() {
        state.selected = null;
        state.route = null;
        state.routeStatus = 'idle';

        map.clearRoute();
        map.highlight(null);
        showBrowse();
    }

    async function requestRoute() {
        const place = state.selected;

        if (!place) {
            return;
        }

        if (!state.user) {
            state.routeStatus = 'no-origin';
            renderDetail();
            map.focusPlace(place);

            return;
        }

        state.routeStatus = 'loading';
        renderDetail();

        try {
            const { route } = await api.route({
                from: state.user,
                to: { lat: place.lat, lng: place.lng },
                profile: state.profile,
            });

            if (destroyed || state.selected?.id !== place.id) {
                return;
            }

            state.route = route;
            state.routeStatus = 'ready';
            map.drawRoute(route.geometry);
            renderDetail();
        } catch (error) {
            if (isAbort(error) || destroyed) {
                return;
            }

            state.routeStatus = 'error';
            state.routeError = error.message;
            renderDetail();
            map.focusPlace(place);
        }
    }

    /* --------------------------------------------------------------- Pintado */

    function renderAccuracy() {
        const accuracy = state.user?.accuracy;

        if (!Number.isFinite(accuracy)) {
            el.accuracy.hidden = true;

            return;
        }

        el.accuracy.hidden = false;
        el.accuracy.textContent = formatAccuracy(accuracy);
        el.accuracy.classList.toggle('is-coarse', accuracy > 100);
        el.accuracy.title = accuracy > 100
            ? 'Precisión baja: activa el GPS para mejores resultados'
            : 'Precisión de la ubicación';
    }

    function renderList() {
        if (!state.places.length) {
            el.list.innerHTML = '';

            return;
        }

        el.list.innerHTML = state.places.map((place) => {
            const meta = [categoryLabels.get(place.category), place.address]
                .filter(Boolean)
                .join(' · ');

            return `<li class="portal-geo__item-row">
                <button type="button" class="portal-geo__item" data-geo-place="${escapeHtml(place.id)}">
                    <span class="portal-geo__item-icon" style="--pin-color:${categoryColor(place.category)}">
                        ${categorySvg(place.category, 16)}
                    </span>
                    <span class="portal-geo__item-copy">
                        <span class="portal-geo__item-name">${escapeHtml(place.name)}</span>
                        <span class="portal-geo__item-meta">${escapeHtml(meta)}</span>
                    </span>
                    <span class="portal-geo__item-distance">${escapeHtml(formatDistance(place.distance))}</span>
                </button>
            </li>`;
        }).join('');
    }

    function renderState() {
        const count = state.places.length;
        const kilometres = state.radius / 1000;

        el.count.textContent = state.status === 'ready' && count
            ? `${count} ${count === 1 ? 'resultado' : 'resultados'} en ${kilometres} km · del más cercano al más lejano`
            : '';

        const templates = {
            locating: `<div class="portal-geo__notice">
                <span class="portal-geo__spinner" aria-hidden="true"></span>
                <p>Buscando tu ubicación…</p>
            </div>`,
            searching: `<div class="portal-geo__skeleton" aria-hidden="true">
                ${'<span></span>'.repeat(4)}
            </div>`,
            empty: `<div class="portal-geo__notice">
                <p class="portal-geo__notice-title">Sin resultados en ${kilometres} km</p>
                <p>Prueba con un radio mayor o activa otras categorías.</p>
            </div>`,
            denied: `<div class="portal-geo__notice portal-geo__notice--warn">
                <p class="portal-geo__notice-title">Necesitamos tu ubicación</p>
                <p>Actívala en los permisos del navegador para ver los centros de salud más cercanos. También puedes mover el mapa y buscar en esa zona.</p>
                <button type="button" class="portal-geo__notice-action" data-geo-retry>Reintentar</button>
            </div>`,
            unavailable: `<div class="portal-geo__notice portal-geo__notice--warn">
                <p class="portal-geo__notice-title">No pudimos ubicarte</p>
                <p>Revisa que el GPS esté encendido y vuelve a intentarlo.</p>
                <button type="button" class="portal-geo__notice-action" data-geo-retry>Reintentar</button>
            </div>`,
            unsupported: `<div class="portal-geo__notice portal-geo__notice--warn">
                <p class="portal-geo__notice-title">Tu navegador no soporta geolocalización</p>
                <p>Mueve el mapa hasta tu zona y usa «Buscar en esta área».</p>
            </div>`,
            error: `<div class="portal-geo__notice portal-geo__notice--warn">
                <p class="portal-geo__notice-title">No pudimos completar la búsqueda</p>
                <p>${escapeHtml(state.errorMessage ?? '')}</p>
                <button type="button" class="portal-geo__notice-action" data-geo-retry-search>Reintentar</button>
            </div>`,
        };

        el.state.innerHTML = templates[state.status] ?? '';
        el.list.hidden = state.status === 'searching';
    }

    function renderDetail() {
        const place = state.selected;

        if (!place) {
            el.detail.innerHTML = '';

            return;
        }

        const metrics = renderMetrics();
        const phone = place.phone
            ? `<a class="portal-geo__action" href="tel:${escapeHtml(place.phone.replace(/\s+/g, ''))}">Llamar</a>`
            : '';

        const hours = place.opening_hours
            ? `<p class="portal-geo__detail-hours">${escapeHtml(place.opening_hours)}</p>`
            : '';

        el.detail.innerHTML = `
            <button type="button" class="portal-geo__back" data-geo-back>
                <span aria-hidden="true">←</span> Volver a la lista
            </button>

            <div class="portal-geo__detail-head">
                <span class="portal-geo__item-icon" style="--pin-color:${categoryColor(place.category)}">
                    ${categorySvg(place.category, 18)}
                </span>
                <div>
                    <h2 class="portal-geo__detail-name">${escapeHtml(place.name)}</h2>
                    <p class="portal-geo__detail-meta">
                        ${escapeHtml([categoryLabels.get(place.category), place.address].filter(Boolean).join(' · '))}
                    </p>
                </div>
            </div>

            ${hours}

            <div class="portal-geo__profiles" role="group" aria-label="Modo de traslado">
                <button type="button" class="portal-geo__profile${state.profile === 'driving' ? ' is-active' : ''}"
                    data-geo-profile="driving" aria-pressed="${state.profile === 'driving'}">En vehículo</button>
                <button type="button" class="portal-geo__profile${state.profile === 'walking' ? ' is-active' : ''}"
                    data-geo-profile="walking" aria-pressed="${state.profile === 'walking'}">A pie</button>
            </div>

            ${metrics}

            <div class="portal-geo__actions">
                <a class="portal-geo__action portal-geo__action--primary"
                   href="${externalDirectionsUrl(place)}" target="_blank" rel="noopener noreferrer">
                    Abrir en mapas
                </a>
                ${phone}
            </div>
        `;
    }

    function renderMetrics() {
        if (state.routeStatus === 'loading') {
            return `<div class="portal-geo__metrics is-loading" aria-busy="true">
                ${'<div><span></span><strong></strong></div>'.repeat(3)}
            </div>`;
        }

        if (state.routeStatus === 'error') {
            return `<div class="portal-geo__notice portal-geo__notice--warn portal-geo__notice--inline">
                <p>${escapeHtml(state.routeError ?? 'No pudimos trazar la ruta.')}</p>
                <button type="button" class="portal-geo__notice-action" data-geo-retry-route>Reintentar</button>
            </div>`;
        }

        if (state.routeStatus === 'no-origin') {
            return `<div class="portal-geo__notice portal-geo__notice--warn portal-geo__notice--inline">
                <p>Activa tu ubicación para trazar la ruta hasta aquí.</p>
            </div>`;
        }

        if (!state.route) {
            return '';
        }

        return `<div class="portal-geo__metrics">
            <div><span>Distancia</span><strong>${formatDistance(state.route.distance)}</strong></div>
            <div><span>Tiempo</span><strong>${formatDuration(state.route.duration)}</strong></div>
            <div><span>Llegada</span><strong>${formatArrival(state.route.duration)}</strong></div>
        </div>`;
    }

    function externalDirectionsUrl(place) {
        const params = new URLSearchParams({
            api: '1',
            destination: `${place.lat},${place.lng}`,
            travelmode: state.profile === 'walking' ? 'walking' : 'driving',
        });

        if (state.user) {
            params.set('origin', `${state.user.lat},${state.user.lng}`);
        }

        return `https://www.google.com/maps/dir/?${params.toString()}`;
    }

    function showDetail() {
        el.browse.hidden = true;
        el.detail.hidden = false;
        el.panel.scrollTop = 0;

        setCollapsedClass(false);
        sheet.snapTo('half', { silent: true });
    }

    function showBrowse() {
        el.detail.hidden = true;
        el.browse.hidden = false;
    }

    function updateSearchAreaButton() {
        const center = map.getCenter();
        const reference = state.searchCenter ?? state.user;

        if (!reference || state.selected) {
            el.searchArea.hidden = true;

            return;
        }

        // Umbral relativo al radio: en 15 km molesta menos que en 5 km.
        const drifted = distanceBetween(center, reference) > state.radius * 0.4;
        el.searchArea.hidden = !drifted || state.status === 'searching';
    }

    /* ---------------------------------------------------------------- Eventos */

    function onChipClick(event) {
        const button = event.currentTarget;
        const key = button.dataset.geoCategory;

        if (key === 'all') {
            state.categories.clear();
        } else if (state.categories.has(key)) {
            state.categories.delete(key);
        } else {
            state.categories.add(key);
        }

        el.chips.forEach((chip) => {
            const isAll = chip.dataset.geoCategory === 'all';
            const active = isAll
                ? state.categories.size === 0
                : state.categories.has(chip.dataset.geoCategory);

            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', String(active));
        });

        scheduleSearch();
    }

    function onRadiusClick(event) {
        const button = event.currentTarget;
        state.radius = Number(button.dataset.geoRadius);

        el.radii.forEach((item) => {
            const active = Number(item.dataset.geoRadius) === state.radius;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', String(active));
        });

        scheduleSearch();
    }

    function onPanelClick(event) {
        const placeButton = event.target.closest('[data-geo-place]');

        if (placeButton) {
            const place = state.places.find((item) => item.id === placeButton.dataset.geoPlace);

            if (place) {
                selectPlace(place);
            }

            return;
        }

        if (event.target.closest('[data-geo-back]')) {
            clearSelection();

            return;
        }

        const profileButton = event.target.closest('[data-geo-profile]');

        if (profileButton) {
            const profile = profileButton.dataset.geoProfile;

            if (profile !== state.profile) {
                state.profile = profile;
                map.clearRoute();
                requestRoute();
            }

            return;
        }

        if (event.target.closest('[data-geo-retry]')) {
            locate();

            return;
        }

        if (event.target.closest('[data-geo-retry-search]')) {
            runSearch();

            return;
        }

        if (event.target.closest('[data-geo-retry-route]')) {
            requestRoute();
        }
    }

    function onRecenter() {
        if (!state.user) {
            locate();

            return;
        }

        state.searchCenter = state.user;
        map.centerOnUser(state.user, 15);
        map.setSearchArea(state.user, state.radius);
        el.searchArea.hidden = true;
    }

    function onSearchArea() {
        state.searchCenter = map.getCenter();
        el.searchArea.hidden = true;
        clearSelection();
        runSearch();
    }

    function onKeyDown(event) {
        if (event.key === 'Escape' && state.selected) {
            clearSelection();
        }
    }

    el.chips.forEach((chip) => chip.addEventListener('click', onChipClick));
    el.radii.forEach((item) => item.addEventListener('click', onRadiusClick));
    el.panel.addEventListener('click', onPanelClick);
    el.collapse.addEventListener('click', toggleCollapsed);
    el.recenter.addEventListener('click', onRecenter);
    el.searchArea.addEventListener('click', onSearchArea);
    document.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', syncTopOffset, { passive: true });
    desktopQuery.addEventListener('change', onBreakpointChange);

    const offMoveEnd = map.onMoveEnd(updateSearchAreaButton);

    // Mientras el mapa esté montado el documento no se desplaza: evita que
    // asome el fondo del portal por debajo del panel.
    document.documentElement.classList.add('portal-geo-lock');

    syncTopOffset();
    map.setView(config.fallbackCenter, 12);
    locate();

    return {
        root,

        destroy() {
            destroyed = true;

            document.documentElement.classList.remove('portal-geo-lock');
            window.clearTimeout(searchTimer);

            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }

            api.abortAll();
            offMoveEnd();

            el.chips.forEach((chip) => chip.removeEventListener('click', onChipClick));
            el.radii.forEach((item) => item.removeEventListener('click', onRadiusClick));
            el.panel.removeEventListener('click', onPanelClick);
            el.collapse.removeEventListener('click', toggleCollapsed);
            el.recenter.removeEventListener('click', onRecenter);
            el.searchArea.removeEventListener('click', onSearchArea);
            document.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('resize', syncTopOffset);
            desktopQuery.removeEventListener('change', onBreakpointChange);

            sheet.destroy();
            map.destroy();
        },
    };
}
