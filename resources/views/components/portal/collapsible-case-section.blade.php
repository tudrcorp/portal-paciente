@props([
    'defaultOpen' => false,
    'documentCount' => null,
    'caseCode' => null,
    /** Si se define, el padre (Alpine) controla abierto/cerrado: isCaseOpen / toggleCaseOpen */
    'caseKey' => null,
    /** Oculta la etiqueta “Documentos” del trigger (útil cuando el conteo va en la fila). */
    'hideDocLabel' => false,
])

@php
    $isControlled = filled($caseKey);
@endphp

<div
    @unless ($isControlled)
        x-data="{ open: @js($defaultOpen) }"
    @endunless
    {{ $attributes->class(['portal-case-collapse']) }}
>
    <div class="portal-case-collapse__shell">
        <button
            type="button"
            @class([
                'portal-case-collapse__trigger w-full text-left',
                'portal-case-collapse__trigger--coded' => filled($caseCode),
                'portal-case-collapse__trigger--row' => $hideDocLabel,
            ])
            @if ($isControlled)
                @click="toggleCaseOpen(@js((string) $caseKey))"
                :aria-expanded="isCaseOpen(@js((string) $caseKey))"
            @else
                @click="open = ! open"
                :aria-expanded="open"
            @endif
        >
            @if (filled($caseCode))
                <flux:badge color="sky" size="sm" class="portal-case-header__code portal-case-header__code--mobile font-mono sm:hidden">
                    {{ $caseCode }}
                </flux:badge>
            @endif

            <div class="portal-case-collapse__main min-w-0">
                {{ $header }}
            </div>

            <div class="portal-case-collapse__actions">
                @if ($documentCount !== null && ! $hideDocLabel)
                    <div class="portal-case-collapse__doc-meta">
                        <span class="portal-case-collapse__doc-label">
                            {{ __('Documentos') }}
                        </span>
                    </div>
                @endif

                <span
                    class="portal-case-collapse__chevron"
                    @if ($isControlled)
                        :class="isCaseOpen(@js((string) $caseKey)) ? 'is-open' : ''"
                    @else
                        :class="open ? 'is-open' : ''"
                    @endif
                    aria-hidden="true"
                >
                    <flux:icon name="chevron-down" class="size-4" />
                </span>
            </div>
        </button>
    </div>

    <div
        @if ($isControlled)
            x-show="isCaseOpen(@js((string) $caseKey))"
        @else
            x-show="open"
        @endif
        x-collapse
        class="portal-case-collapse__panel"
    >
        <div class="portal-doc-list px-3 py-3 sm:px-5 sm:py-4">
            <div class="portal-doc-list__head" aria-hidden="true">
                <span>{{ __('Documento') }}</span>
                <span>{{ __('Categoría') }}</span>
                <span>{{ __('Tipo') }}</span>
                <span>{{ __('Fecha') }}</span>
                <span>{{ __('Acción') }}</span>
            </div>

            <div class="portal-doc-list__body" role="list">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
