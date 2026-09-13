/**
 * Cliente de los endpoints propios de geolocalización.
 *
 * Cada tipo de consulta mantiene un único AbortController: si el paciente
 * cambia de filtro mientras la anterior sigue en vuelo, la vieja se cancela.
 * Así nunca se pinta un resultado que ya no corresponde a lo seleccionado.
 */
export function createGeoApi(endpoints) {
    const controllers = new Map();

    function abort(kind) {
        controllers.get(kind)?.abort();
        controllers.delete(kind);
    }

    async function request(kind, url, params) {
        abort(kind);

        const controller = new AbortController();
        controllers.set(kind, controller);

        const query = new URLSearchParams();

        Object.entries(params).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => query.append(`${key}[]`, item));

                return;
            }

            query.append(key, value);
        });

        let response;

        try {
            response = await fetch(`${url}?${query.toString()}`, {
                method: 'GET',
                signal: controller.signal,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
        } finally {
            if (controllers.get(kind) === controller) {
                controllers.delete(kind);
            }
        }

        if (response.status === 419 || response.status === 401) {
            throw new Error('Tu sesión expiró. Vuelve a iniciar sesión para seguir buscando.');
        }

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'No pudimos completar la búsqueda. Intenta de nuevo.');
        }

        return payload;
    }

    return {
        places: ({ lat, lng, radius, categories }) =>
            request('places', endpoints.places, { lat, lng, radius, categories }),

        route: ({ from, to, profile }) =>
            request('route', endpoints.route, {
                from_lat: from.lat,
                from_lng: from.lng,
                to_lat: to.lat,
                to_lng: to.lng,
                profile,
            }),

        address: ({ lat, lng }) => request('address', endpoints.address, { lat, lng }),

        abortAll() {
            controllers.forEach((controller) => controller.abort());
            controllers.clear();
        },
    };
}

/** Una promesa cancelada no es un error que deba verse en pantalla. */
export function isAbort(error) {
    return error?.name === 'AbortError';
}
