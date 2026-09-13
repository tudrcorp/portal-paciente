/**
 * Identidad visual de cada categoría: un color y un glifo. Se usa igual en los
 * pines del mapa, en los chips del panel y en la ficha de detalle, para que el
 * paciente asocie color y tipo de centro de un vistazo.
 */
const GLYPHS = {
    // Cruz sanitaria.
    pharmacy: 'M10 2.5h4a1 1 0 0 1 1 1V9h5.5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H15v5.5a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V15H3.5a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1H9V3.5a1 1 0 0 1 1-1Z',
    // Edificio hospitalario.
    hospital: 'M4 21.5a1 1 0 0 1-1-1V8.2a1 1 0 0 1 .55-.9l8-4a1 1 0 0 1 .9 0l8 4a1 1 0 0 1 .55.9v12.3a1 1 0 0 1-1 1h-5.5V17a2.5 2.5 0 0 0-5 0v4.5H4Zm7.25-12.25V11H9.5v1.5h1.75v1.75h1.5V12.5H14.5V11h-1.75V9.25h-1.5Z',
    // Corazón: atención asistencial.
    clinic: 'M12 20.7s-7.6-4.6-9.7-9A5.9 5.9 0 0 1 12 5.9a5.9 5.9 0 0 1 9.7 5.8c-2.1 4.4-9.7 9-9.7 9Z',
    // Latido: control y seguimiento.
    ambulatory: 'M2.8 12.8a1 1 0 0 1 0-2h3.6l2.2-5.1a1 1 0 0 1 1.86.08l2.72 8.6 1.76-3.35a1 1 0 0 1 .88-.53h4.5a1 1 0 1 1 0 2h-3.9l-2.66 5.06a1 1 0 0 1-1.84-.16L9.2 8.9l-1.4 3.28a1 1 0 0 1-.92.61H2.8Z',
};

const COLORS = {
    pharmacy: '#1f8a5f',
    hospital: '#c8443c',
    clinic: '#4474a3',
    ambulatory: '#b07a1e',
};

const FALLBACK = 'clinic';

export function categoryColor(category) {
    return COLORS[category] ?? COLORS[FALLBACK];
}

export function categoryGlyph(category) {
    return GLYPHS[category] ?? GLYPHS[FALLBACK];
}

/** SVG del glifo de la categoría, listo para incrustar. */
export function categorySvg(category, size = 14) {
    return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" aria-hidden="true" focusable="false"><path fill="currentColor" d="${categoryGlyph(category)}"/></svg>`;
}

/** Marcador del mapa. */
export function pinMarkup(category) {
    return `<span class="portal-geo-pin" style="--pin-color:${categoryColor(category)}">
        <span class="portal-geo-pin__body">${categorySvg(category, 15)}</span>
    </span>`;
}

/** Posición del paciente: punto con halo de precisión. */
export function userMarkup() {
    return `<span class="portal-geo-user" aria-hidden="true">
        <span class="portal-geo-user__pulse"></span>
        <span class="portal-geo-user__dot"></span>
    </span>`;
}
