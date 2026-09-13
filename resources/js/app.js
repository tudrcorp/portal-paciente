/**
 * Punto de entrada global del portal.
 *
 * Solo contiene el arranque de módulos pesados bajo demanda: el mapa de
 * «Cerca de mí» pesa bastante más que el resto del portal junto, así que se
 * carga como chunk aparte y únicamente cuando la pantalla está en el DOM.
 */

let activeNearby = null;

async function syncNearby() {
    const root = document.querySelector('[data-geo-root]');

    if (!root) {
        activeNearby?.destroy();
        activeNearby = null;

        return;
    }

    // Ya montado sobre este mismo nodo: nada que hacer.
    if (activeNearby?.root === root) {
        return;
    }

    activeNearby?.destroy();
    activeNearby = null;

    const { mountNearby } = await import('./nearby/index.js');

    // Entre el await y aquí el paciente pudo navegar a otra pantalla.
    if (!root.isConnected) {
        return;
    }

    activeNearby = mountNearby(root);
}

document.addEventListener('DOMContentLoaded', syncNearby);
document.addEventListener('livewire:navigated', syncNearby);

// Livewire desmonta el DOM anterior antes de pintar el nuevo: es el momento
// de soltar los listeners del mapa y evitar fugas entre navegaciones SPA.
document.addEventListener('livewire:navigating', () => {
    activeNearby?.destroy();
    activeNearby = null;
});
