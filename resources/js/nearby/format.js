const LOCALE = 'es-VE';

/** Metros → «450 m» / «2,3 km». */
export function formatDistance(meters) {
    if (!Number.isFinite(meters)) {
        return '';
    }

    if (meters < 1000) {
        return `${Math.round(meters)} m`;
    }

    const km = meters / 1000;
    const value = km.toLocaleString(LOCALE, {
        minimumFractionDigits: km < 10 ? 1 : 0,
        maximumFractionDigits: km < 10 ? 1 : 0,
    });

    return `${value} km`;
}

/** Segundos → «8 min» / «1 h 24 min». */
export function formatDuration(seconds) {
    if (!Number.isFinite(seconds)) {
        return '';
    }

    const total = Math.max(1, Math.round(seconds / 60));

    if (total < 60) {
        return `${total} min`;
    }

    const hours = Math.floor(total / 60);
    const minutes = total % 60;

    return minutes === 0 ? `${hours} h` : `${hours} h ${minutes} min`;
}

/** Hora estimada de llegada a partir de ahora. */
export function formatArrival(seconds) {
    if (!Number.isFinite(seconds)) {
        return '';
    }

    const arrival = new Date(Date.now() + seconds * 1000);

    return arrival.toLocaleTimeString(LOCALE, { hour: '2-digit', minute: '2-digit' });
}

export function formatAccuracy(meters) {
    if (!Number.isFinite(meters)) {
        return '';
    }

    return `±${formatDistance(meters)}`;
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
