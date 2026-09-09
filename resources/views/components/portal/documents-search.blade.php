@props([
    'filterOptions',
    'totalDocuments' => 0,
])

<div {{ $attributes->class(['portal-documents-search']) }}>
    <div class="portal-documents-search__bar">
        <label class="portal-documents-search__field">
            <span class="sr-only">{{ __('Buscar documentos') }}</span>
            <span class="portal-documents-search__icon" aria-hidden="true">
                <flux:icon name="magnifying-glass" class="size-5" />
            </span>
            <input
                type="search"
                x-model.debounce.150ms="query"
                placeholder="{{ __('Buscar') }}"
                autocomplete="off"
                class="portal-documents-search__input"
                data-test="documents-search-input"
            />
        </label>

        <button
            type="button"
            class="portal-documents-search__filter"
            @click="dateFiltersOpen = ! dateFiltersOpen"
            :aria-expanded="dateFiltersOpen.toString()"
            aria-controls="documents-date-filters"
            :aria-label="dateFiltersOpen ? @js(__('Ocultar filtros de fecha')) : @js(__('Mostrar filtros de fecha'))"
            data-test="documents-date-filters-toggle"
        >
            <flux:icon name="funnel" class="size-5" />
            <span
                class="portal-documents-search__badge"
                x-text="activeDateFilterCount()"
                :class="activeDateFilterCount() > 0 ? 'is-active' : ''"
            >0</span>
        </button>
    </div>

    <div
        id="documents-date-filters"
        x-show="dateFiltersOpen"
        x-collapse
        x-cloak
        class="portal-documents-search__panel"
    >
        <div class="portal-documents-date-row grid w-full max-w-full grid-cols-1 gap-3 md:grid-cols-4">
            <flux:field class="min-w-0 w-full max-w-full">
                <flux:label>{{ __('Desde') }}</flux:label>
                <div class="portal-date-input-shell">
                    <input
                        type="date"
                        x-model="dateFrom"
                        @if (filled($filterOptions['date_min'] ?? null)) min="{{ $filterOptions['date_min'] }}" @endif
                        @if (filled($filterOptions['date_max'] ?? null)) max="{{ $filterOptions['date_max'] }}" @endif
                        class="portal-date-input"
                    />
                </div>
            </flux:field>

            <flux:field class="min-w-0 w-full max-w-full">
                <flux:label>{{ __('Hasta') }}</flux:label>
                <div class="portal-date-input-shell">
                    <input
                        type="date"
                        x-model="dateTo"
                        @if (filled($filterOptions['date_min'] ?? null)) min="{{ $filterOptions['date_min'] }}" @endif
                        @if (filled($filterOptions['date_max'] ?? null)) max="{{ $filterOptions['date_max'] }}" @endif
                        class="portal-date-input"
                    />
                </div>
            </flux:field>

            <flux:field class="min-w-0">
                <flux:label>{{ __('Mes') }}</flux:label>
                <flux:select x-model="month">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach ($filterOptions['months'] as $monthOption)
                        <option value="{{ $monthOption['value'] }}">{{ $monthOption['label'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="min-w-0">
                <flux:label>{{ __('Año') }}</flux:label>
                <flux:select x-model="year">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach (($filterOptions['years'] ?? []) as $yearOption)
                        <option value="{{ $yearOption['value'] }}">{{ $yearOption['label'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs leading-relaxed text-portal-muted dark:text-zinc-400">
                {{ __('Rango, mes y año se combinan: deben cumplirse todos.') }}
            </p>

            <button
                type="button"
                class="text-xs font-semibold text-portal-primary underline-offset-2 hover:underline dark:text-portal-primary"
                x-show="hasDateFilters()"
                x-cloak
                @click="dateFrom = ''; dateTo = ''; year = ''; month = ''"
            >
                {{ __('Limpiar fechas') }}
            </button>
        </div>

        <p
            x-show="dateRangeInvalid()"
            x-cloak
            class="mt-3 rounded-xl border border-amber-300/40 bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-400/30 dark:bg-amber-950/35 dark:text-amber-200"
        >
            {{ __('La fecha “Desde” no puede ser posterior a la fecha “Hasta”. Ajusta el rango para continuar.') }}
        </p>
    </div>

    <div x-show="hasActiveFilters()" x-cloak class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <p
            x-show="!dateRangeInvalid()"
            class="text-xs text-portal-muted dark:text-zinc-400"
            x-text="activeFiltersLabel()"
        ></p>
        <flux:button
            size="sm"
            variant="ghost"
            icon="x-mark"
            type="button"
            @click="clearFilters()"
        >
            {{ __('Limpiar búsqueda') }}
        </flux:button>
    </div>
</div>
