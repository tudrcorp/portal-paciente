@props([
    'inline' => false,
])

<div
    {{ $attributes->class([
        'portal-theme-toolbar',
        'portal-theme-toolbar--inline' => $inline,
    ]) }}
    x-data="{
        get isDark() {
            const mode = $flux.appearance ?? 'system';

            if (mode === 'dark') {
                return true;
            }

            if (mode === 'light') {
                return false;
            }

            return window.matchMedia('(prefers-color-scheme: dark)').matches;
        },
        toggle() {
            $flux.appearance = this.isDark ? 'light' : 'dark';
        },
    }"
    data-test="portal-theme-switcher"
>
    <button
        type="button"
        role="switch"
        class="portal-theme-toggle"
        :class="($flux.appearance === 'dark' || ($flux.appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) ? 'is-dark' : 'is-light'"
        :aria-checked="($flux.appearance === 'dark' || ($flux.appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches))"
        :aria-label="($flux.appearance === 'dark' || ($flux.appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) ? '{{ __('Cambiar a tema claro') }}' : '{{ __('Cambiar a tema oscuro') }}'"
        @click="toggle()"
        data-test="portal-theme-toggle"
    >
        <span class="portal-theme-toggle__track">
            <span class="portal-theme-toggle__icon portal-theme-toggle__icon--sun" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <circle cx="12" cy="12" r="4.25" />
                    <path stroke-linecap="round" d="M12 2.25v2.1M12 19.65v2.1M4.22 4.22l1.48 1.48M18.3 18.3l1.48 1.48M2.25 12h2.1M19.65 12h2.1M4.22 19.78l1.48-1.48M18.3 5.7l1.48-1.48" />
                </svg>
            </span>

            <span class="portal-theme-toggle__thumb" aria-hidden="true"></span>

            <span class="portal-theme-toggle__icon portal-theme-toggle__icon--moon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.74 2.5a8.25 8.25 0 1 0 8.76 8.76 6.5 6.5 0 1 1-8.76-8.76Z" />
                </svg>
                <svg class="portal-theme-toggle__star portal-theme-toggle__star--one" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true">
                    <path d="M4 0 4.7 2.7 7.4 3.4 4.7 4.1 4 6.8 3.3 4.1.6 3.4 3.3 2.7Z" />
                </svg>
                <svg class="portal-theme-toggle__star portal-theme-toggle__star--two" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true">
                    <path d="M4 0 4.7 2.7 7.4 3.4 4.7 4.1 4 6.8 3.3 4.1.6 3.4 3.3 2.7Z" />
                </svg>
            </span>
        </span>
    </button>
</div>
