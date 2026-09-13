/**
 * Panel inferior arrastrable (solo en móvil/tablet).
 *
 * Se mueve con `transform`, nunca con `height`: animar la altura obligaría al
 * navegador a recalcular el layout del listado en cada frame y en gama baja se
 * nota. Tres posiciones: asomado, medio y completo.
 */
const SNAPS = ['peek', 'half', 'full'];

export function createSheet(sheet, { handles = [], onSnap } = {}) {
    const desktopQuery = window.matchMedia('(min-width: 1024px)');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    let offsets = { peek: 0, half: 0, full: 0 };
    let current = 'half';
    let drag = null;

    function measure() {
        const container = sheet.parentElement;
        const containerHeight = container?.clientHeight || window.innerHeight;
        const sheetHeight = sheet.offsetHeight;
        const peekHeight = sheet.querySelector('[data-geo-origin-block]')?.offsetHeight
            || Math.min(168, sheetHeight);

        offsets = {
            full: 0,
            half: Math.max(0, sheetHeight - Math.round(containerHeight * 0.45)),
            peek: Math.max(0, sheetHeight - peekHeight),
        };
    }

    function apply(animated = true) {
        if (desktopQuery.matches) {
            sheet.style.transform = '';

            return;
        }

        sheet.style.transition = animated && !reduceMotion.matches
            ? 'transform var(--portal-duration-base) var(--portal-ease-ios-spring)'
            : 'none';
        sheet.style.transform = `translate3d(0, ${offsets[current]}px, 0)`;
        sheet.dataset.snap = current;
    }

    function snapTo(name, { animated = true, silent = false } = {}) {
        if (!SNAPS.includes(name) || desktopQuery.matches) {
            return;
        }

        const changed = current !== name;
        current = name;
        apply(animated);

        if (changed && !silent) {
            onSnap?.(current);
        }
    }

    function nearestSnap(offset, velocity) {
        // Un gesto rápido manda sobre la distancia: es lo que espera el pulgar.
        if (Math.abs(velocity) > 0.45) {
            const index = SNAPS.indexOf(current);
            const next = velocity < 0 ? index + 1 : index - 1;

            return SNAPS[Math.min(SNAPS.length - 1, Math.max(0, next))];
        }

        return SNAPS.reduce((best, name) =>
            Math.abs(offsets[name] - offset) < Math.abs(offsets[best] - offset) ? name : best
        , SNAPS[0]);
    }

    function onPointerDown(event) {
        if (desktopQuery.matches || event.button > 0) {
            return;
        }

        // Los controles de la cabecera (minimizar) no deben iniciar arrastre.
        if (event.target.closest('[data-geo-no-drag]')) {
            return;
        }

        drag = {
            id: event.pointerId,
            // Solo el asa alterna con un toque simple: tocar la dirección no
            // debería mover el panel sin querer.
            fromHandle: Boolean(event.target.closest('[data-geo-handle]')),
            startY: event.clientY,
            startOffset: offsets[current],
            lastY: event.clientY,
            lastTime: event.timeStamp,
            velocity: 0,
            moved: false,
            target: event.currentTarget,
        };

        event.currentTarget.setPointerCapture(event.pointerId);
        sheet.style.transition = 'none';
    }

    function onPointerMove(event) {
        if (!drag || event.pointerId !== drag.id) {
            return;
        }

        const delta = event.clientY - drag.startY;

        if (!drag.moved && Math.abs(delta) < 3) {
            return;
        }

        drag.moved = true;

        const elapsed = Math.max(1, event.timeStamp - drag.lastTime);
        drag.velocity = (event.clientY - drag.lastY) / elapsed;
        drag.lastY = event.clientY;
        drag.lastTime = event.timeStamp;

        // Resistencia al pasar de los topes: comunica el límite sin frenar seco.
        let offset = drag.startOffset + delta;
        const min = offsets.full;
        const max = offsets.peek;

        if (offset < min) {
            offset = min - (min - offset) * 0.25;
        } else if (offset > max) {
            offset = max + (offset - max) * 0.25;
        }

        sheet.style.transform = `translate3d(0, ${offset}px, 0)`;
    }

    function onPointerUp(event) {
        if (!drag || event.pointerId !== drag.id) {
            return;
        }

        const wasDragged = drag.moved;
        const fromHandle = drag.fromHandle;
        const offset = drag.startOffset + (event.clientY - drag.startY);
        const velocity = drag.velocity;

        drag.target.releasePointerCapture?.(event.pointerId);
        drag = null;

        if (!wasDragged) {
            // Toque simple sobre el asa: alterna entre medio y completo.
            if (fromHandle) {
                snapTo(current === 'full' ? 'half' : 'full');
            }

            return;
        }

        snapTo(nearestSnap(offset, velocity));
    }

    function onResize() {
        measure();
        apply(false);
    }

    handles.forEach((handle) => {
        handle.addEventListener('pointerdown', onPointerDown);
        handle.addEventListener('pointermove', onPointerMove);
        handle.addEventListener('pointerup', onPointerUp);
        handle.addEventListener('pointercancel', onPointerUp);
    });

    window.addEventListener('resize', onResize, { passive: true });
    desktopQuery.addEventListener('change', onResize);

    measure();
    apply(false);

    return {
        snapTo,
        refresh: onResize,
        current: () => current,
        isDesktop: () => desktopQuery.matches,
        destroy() {
            handles.forEach((handle) => {
                handle.removeEventListener('pointerdown', onPointerDown);
                handle.removeEventListener('pointermove', onPointerMove);
                handle.removeEventListener('pointerup', onPointerUp);
                handle.removeEventListener('pointercancel', onPointerUp);
            });

            window.removeEventListener('resize', onResize);
            desktopQuery.removeEventListener('change', onResize);
        },
    };
}
