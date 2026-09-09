@php
    use App\Support\ClinicalHistoryPresentation;
@endphp

<x-layouts.app :title="__('Historia clínica')">
    @php
        $pathologicalMap = ClinicalHistoryPresentation::pathologicalFlags();
        $appMap = ClinicalHistoryPresentation::appDeclaredFlags();
        $habitMap = ClinicalHistoryPresentation::habitFlags();

        $safeCount = function (array $map) use ($history): int {
            if (! $history) {
                return 0;
            }

            return collect($map)
                ->keys()
                ->filter(fn ($column) => array_key_exists($column, $history->getAttributes()) && (bool) $history->{$column} === true)
                ->count();
        };

        $positiveLabels = function (array $map) use ($history): array {
            if (! $history) {
                return [];
            }

            return collect($map)
                ->filter(fn ($label, $column) => array_key_exists($column, $history->getAttributes()) && (bool) $history->{$column} === true)
                ->values()
                ->all();
        };

        $pathologicalPositive = $safeCount($pathologicalMap);
        $appPositive = $safeCount($appMap);
        $habitPositive = $safeCount($habitMap);

        $pathologicalPositiveLabels = $positiveLabels($pathologicalMap);
        $appPositiveLabels = $positiveLabels($appMap);
        $habitPositiveLabels = $positiveLabels($habitMap);

        $familyText = ClinicalHistoryPresentation::formatClinicalText($history?->section_family_observations);
        $pathologicalExtraText = ClinicalHistoryPresentation::formatClinicalText($history?->section_pathological_observations);
        $surgicalExtraText = ClinicalHistoryPresentation::formatClinicalText($history?->section_surgical_observations);
        $gynExtraText = ClinicalHistoryPresentation::formatClinicalText($history?->section_gynecological_observations);
        $habitsExtraText = ClinicalHistoryPresentation::formatClinicalText($history?->section_no_pathological_observations);

        $allergiesText = ClinicalHistoryPresentation::formatClinicalText($history?->allergies);
        $allergiesObsText = ClinicalHistoryPresentation::formatClinicalText($history?->observations_allergies);
        $surgicalText = ClinicalHistoryPresentation::formatClinicalText($history?->history_surgical);
        $medicationsText = ClinicalHistoryPresentation::formatClinicalText($history?->medications_supplements);
        $medicationsObsText = ClinicalHistoryPresentation::formatClinicalText($history?->observations_medication);
        $pathologicalObsText = ClinicalHistoryPresentation::formatClinicalText($history?->observations_pathological);
        $personalObsText = ClinicalHistoryPresentation::formatClinicalText($history?->observations_personal);
        $diagnosisObsText = ClinicalHistoryPresentation::formatClinicalText($history?->observations_diagnosis);
        $notPathologicalObsText = ClinicalHistoryPresentation::formatClinicalText($history?->observations_not_pathological);
        $gynObsText = ClinicalHistoryPresentation::formatClinicalText($history?->observations_ginecologica);

        $allergyCount = collect([$allergiesText, $allergiesObsText])->filter()->count();
        $surgicalCount = collect([$surgicalText, $surgicalExtraText])->filter()->count();
        $medicationCount = collect([$medicationsText, $medicationsObsText])->filter()->count();
        $familyCount = $familyText ? 1 : 0;
        $habitsSocialCount = $habitPositive
            + collect([$notPathologicalObsText, $habitsExtraText])->filter()->count();
        $pathologicalCount = $pathologicalPositive + $appPositive
            + collect([$pathologicalObsText, $pathologicalExtraText])->filter()->count();

        $gynFields = [
            'edad_primera_menstruation' => $history?->edad_primera_menstruation,
            'fecha_ultima_regla' => $history?->fecha_ultima_regla,
            'numero_embarazos' => $history?->numero_embarazos,
            'numero_partos' => $history?->numero_partos,
            'numero_abortos' => $history?->numero_abortos,
            'cesareas' => $history?->cesareas,
        ];
        $gynFieldsFilled = collect($gynFields)->filter(fn ($value) => filled($value))->count();
        $gynCount = $gynFieldsFilled + collect([$gynObsText, $gynExtraText])->filter()->count();

        $generalInfoCount = collect([
            $history?->code,
            $history?->history_date,
            $personalObsText,
            $diagnosisObsText,
            $patient?->full_name,
            $patient?->nro_identificacion,
            $patient?->birth_date,
            $patient?->sex,
            $patient?->phone,
            $patient?->email,
        ])->filter(fn ($value) => filled($value))->count();

        $groupByPriority = function (array $labels): array {
            $grouped = [
                'alta' => [],
                'media' => [],
                'baja' => [],
            ];

            foreach ($labels as $label) {
                $text = mb_strtolower((string) $label);

                $isHigh =
                    str_contains($text, 'hipert')
                    || str_contains($text, 'diabet')
                    || str_contains($text, 'cáncer')
                    || str_contains($text, 'cancer')
                    || str_contains($text, 'card')
                    || str_contains($text, 'infarto')
                    || str_contains($text, 'renal')
                    || str_contains($text, 'epilep')
                    || str_contains($text, 'asma')
                    || str_contains($text, 'alerg')
                    || str_contains($text, 'cirug');

                $isMedium =
                    str_contains($text, 'sobrepeso')
                    || str_contains($text, 'obes')
                    || str_contains($text, 'tabac')
                    || str_contains($text, 'fuma')
                    || str_contains($text, 'alcohol')
                    || str_contains($text, 'sedentar')
                    || str_contains($text, 'ansiedad')
                    || str_contains($text, 'depres');

                if ($isHigh) {
                    $grouped['alta'][] = $label;
                } elseif ($isMedium) {
                    $grouped['media'][] = $label;
                } else {
                    $grouped['baja'][] = $label;
                }
            }

            return $grouped;
        };

        $pathologicalByPriority = $groupByPriority($pathologicalPositiveLabels);
        $habitByPriority = $groupByPriority($habitPositiveLabels);
        $appByPriority = $groupByPriority($appPositiveLabels);

        $sexLabel = match (mb_strtolower((string) ($patient?->sex ?? ''))) {
            'f', 'femenino', 'female', 'mujer' => __('Femenino'),
            'm', 'masculino', 'male', 'hombre' => __('Masculino'),
            default => filled($patient?->sex) ? (string) $patient->sex : null,
        };

        $tabs = [
            ['id' => 'informacion-general', 'label' => __('Información general'), 'icon' => 'identification', 'count' => $generalInfoCount],
            ['id' => 'familiares', 'label' => __('Antecedentes Familiares'), 'icon' => 'user-group', 'count' => $familyCount],
            ['id' => 'patologicos', 'label' => __('Antecedentes Patológicos'), 'icon' => 'beaker', 'count' => $pathologicalCount],
            ['id' => 'habitos', 'label' => __('Hábitos y social'), 'icon' => 'fire', 'count' => $habitsSocialCount],
            ['id' => 'quirurgicos', 'label' => __('Quirúrgicos'), 'icon' => 'scissors', 'count' => $surgicalCount],
            ['id' => 'alergias', 'label' => __('Alergias'), 'icon' => 'exclamation-triangle', 'count' => $allergyCount],
            ['id' => 'medicamentos', 'label' => __('Medicamentos'), 'icon' => 'archive-box', 'count' => $medicationCount],
            ['id' => 'ginecologicos', 'label' => __('Ginecológicos'), 'icon' => 'heart', 'count' => $gynCount],
        ];
    @endphp

    <div class="portal-stack-appear portal-typography-fluid mx-auto flex w-full max-w-[1220px] flex-col gap-6 motion-safe:animate-portal-fade xl:gap-8">
        <header class="flex flex-col gap-4 border-b border-portal-border-soft py-6 dark:border-zinc-700">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div class="flex items-start gap-4">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-portal-primary-soft text-portal-primary ring-1 ring-portal-primary/20 dark:bg-portal-primary-soft dark:text-portal-primary">
                        <flux:icon.document-text class="size-6" />
                    </span>
                    <div>
                        <flux:heading size="xl" class="font-semibold tracking-tight text-portal-ink dark:text-white">
                            {{ __('Historia clínica') }}
                        </flux:heading>
                        <flux:subheading class="mt-1 max-w-2xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                            {{ __('Fecha del Registro: :date', ['date' => $history->history_date]) }}
                        </flux:subheading>
                        <flux:subheading class="mt-1 max-w-2xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                            {{ __('Usa las pestañas para encontrar cada tipo de información.') }}
                        </flux:subheading>
                    </div>
                </div>
                @if ($history)
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button
                            variant="primary"
                            size="sm"
                            icon="arrow-down-tray"
                            :href="route('history.download')"
                            class="portal-glass-button"
                            data-test="download-clinical-history-pdf"
                        >
                            {{ __('Descargar PDF') }}
                        </flux:button>
                    </div>
                @endif
            </div>
        </header>

        @if (! $isPatient)
            <x-portal.alert variant="warning" :title="__('Cuenta no vinculada a paciente')">
                {{ __('Esta sección está disponible para pacientes autenticados desde la base clínica.') }}
            </x-portal.alert>
        @elseif (! $history)
            <x-portal.empty-state
                :title="__('Aún no hay historia clínica registrada')"
                :description="__('Cuando tu médico registre tu historia en el sistema, podrás revisarla aquí de manera clara y organizada.')"
            >
                <x-slot name="icon">
                    <flux:icon.folder-open class="size-7 text-portal-primary dark:text-portal-primary" />
                </x-slot>
                <x-slot name="actions">
                    <flux:button variant="primary" :href="route('dashboard')" wire:navigate icon="home">
                        {{ __('Volver al inicio') }}
                    </flux:button>
                </x-slot>
            </x-portal.empty-state>
        @else
            <div
                x-data="{
                    tab: 'informacion-general',
                    segReady: false,
                    storageKey: 'portal-clinical-history-tab-v2',
                    init() {
                        const saved = localStorage.getItem(this.storageKey);
                        const valid = @js(collect($tabs)->pluck('id')->all());
                        if (saved && valid.includes(saved)) {
                            this.tab = saved;
                        }
                        this.$nextTick(() => this.setIndicator());
                        window.addEventListener('resize', () => this.setIndicator());
                    },
                    selectTab(id) {
                        this.tab = id;
                        localStorage.setItem(this.storageKey, id);
                        this.$nextTick(() => this.setIndicator());
                    },
                    setIndicator() {
                        const link = this.$root.querySelector(`[data-tab='${this.tab}']`);
                        if (!link) return;
                        const nav = this.$refs.tabNav;
                        nav.style.setProperty('--seg-x', `${link.offsetLeft}px`);
                        nav.style.setProperty('--seg-w', `${link.offsetWidth}px`);
                        this.segReady = true;
                    },
                }"
                class="flex flex-col gap-5"
            >
                <nav
                    x-ref="tabNav"
                    class="portal-segmented-nav portal-segmented-nav-enhanced -mx-1"
                    :data-seg-ready="segReady ? 'true' : 'false'"
                    aria-label="{{ __('Secciones de historia clínica') }}"
                    role="tablist"
                >
                    <div class="portal-segmented-track">
                        <span class="portal-segmented-indicator" aria-hidden="true"></span>
                        @foreach ($tabs as $tabItem)
                            <button
                                type="button"
                                role="tab"
                                data-tab="{{ $tabItem['id'] }}"
                                :id="'tab-' + @js($tabItem['id'])"
                                :aria-selected="tab === @js($tabItem['id'])"
                                :aria-controls="'panel-' + @js($tabItem['id'])"
                                :class="tab === @js($tabItem['id']) ? 'portal-segmented-link-active' : ''"
                                class="portal-segmented-link"
                                @click="selectTab(@js($tabItem['id']))"
                            >
                                <flux:icon :icon="$tabItem['icon']" class="size-4 shrink-0" />
                                <span>{{ $tabItem['label'] }}</span>
                                @if (($tabItem['count'] ?? 0) > 0)
                                    <span class="portal-tab-badge">{{ $tabItem['count'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </nav>

                {{-- Información general --}}
                <div
                    x-show="tab === 'informacion-general'"
                    x-cloak
                    role="tabpanel"
                    id="panel-informacion-general"
                    aria-labelledby="tab-informacion-general"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Información general') }}</h2>
                            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                                {{ __('Datos del paciente y del registro de historia clínica.') }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @if (filled($patient?->full_name))
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Nombre') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $patient->full_name }}</p>
                                </div>
                            @endif
                            @if (filled($patient?->nro_identificacion))
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Identificación') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $patient->nro_identificacion }}</p>
                                </div>
                            @endif
                            @if (filled($patient?->birth_date))
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Fecha de nacimiento') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $patient->birth_date }}</p>
                                </div>
                            @endif
                            @if ($sexLabel)
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Sexo') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $sexLabel }}</p>
                                </div>
                            @endif
                            @if (filled($history->code))
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Código de historia') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->code }}</p>
                                </div>
                            @endif
                            @if (filled($history->history_date))
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Fecha del registro') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->history_date }}</p>
                                </div>
                            @endif
                            @if (filled($patient?->phone))
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Teléfono') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $patient->phone }}</p>
                                </div>
                            @endif
                            @if (filled($patient?->email))
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                    <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Correo') }}</p>
                                    <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $patient->email }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 space-y-4">
                            <x-clinical.text-block :label="__('Observaciones personales')" :content="$personalObsText" />
                            <x-clinical.text-block :label="__('Observaciones de diagnóstico')" :content="$diagnosisObsText" />
                        </div>
                    </x-glass-panel>
                </div>

                {{-- Familiares --}}
                <div
                    x-show="tab === 'familiares'"
                    x-cloak
                    role="tabpanel"
                    id="panel-familiares"
                    aria-labelledby="tab-familiares"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes familiares') }}</h2>
                            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                                {{ __('Enfermedades o condiciones reportadas en tu familia.') }}
                            </p>
                        </div>

                        @if (! $familyText)
                            <x-portal.empty-state
                                class="!py-8"
                                :title="__('Sin antecedentes familiares')"
                                :description="__('No hay antecedentes familiares documentados en este registro.')"
                            >
                                <x-slot name="icon">
                                    <flux:icon.user-group class="size-7 text-portal-primary dark:text-portal-primary" />
                                </x-slot>
                            </x-portal.empty-state>
                        @else
                            <x-clinical.text-block :label="__('Observaciones')" :content="$familyText" />
                        @endif
                    </x-glass-panel>
                </div>

                {{-- Patológicos --}}
                <div
                    x-show="tab === 'patologicos'"
                    x-cloak
                    role="tabpanel"
                    id="panel-patologicos"
                    aria-labelledby="tab-patologicos"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes patológicos') }}</h2>
                                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">{{ __('Condiciones o enfermedades reportadas en tu historial clínico.') }}</p>
                            </div>
                            @if ($pathologicalPositive > 0)
                                <flux:badge color="sky" size="sm">{{ __(':count reportados', ['count' => $pathologicalPositive]) }}</flux:badge>
                            @endif
                        </div>

                        <x-clinical.priority-groups
                            :groups="$pathologicalByPriority"
                            :empty="__('No se reportaron antecedentes patológicos en este registro.')"
                        />

                        <x-clinical.text-block class="mt-4" :label="__('Observaciones del médico (patológicos)')" :content="$pathologicalObsText" />
                        <x-clinical.text-block class="mt-4" :label="__('Observaciones adicionales')" :content="$pathologicalExtraText" />

                        @if ($appPositive > 0)
                            <div class="mt-6 border-t border-portal-border-soft pt-5 dark:border-zinc-700">
                                <h3 class="text-sm font-semibold text-portal-ink dark:text-zinc-100">{{ __('Declarados en la aplicación') }}</h3>
                                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                                    {{ __('Respuestas registradas desde la app de telemedicina.') }}
                                </p>
                                <div class="mt-3">
                                    <x-clinical.priority-groups :groups="$appByPriority" />
                                </div>
                            </div>
                        @endif
                    </x-glass-panel>
                </div>

                {{-- Hábitos y social --}}
                <div
                    x-show="tab === 'habitos'"
                    x-cloak
                    role="tabpanel"
                    id="panel-habitos"
                    aria-labelledby="tab-habitos"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Hábitos y social') }}</h2>
                                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">{{ __('Factores de estilo de vida y antecedentes no patológicos.') }}</p>
                            </div>
                            @if ($habitPositive > 0)
                                <flux:badge color="amber" size="sm">{{ __(':count marcados', ['count' => $habitPositive]) }}</flux:badge>
                            @endif
                        </div>

                        <x-clinical.priority-groups
                            :groups="$habitByPriority"
                            :empty="__('No se marcaron hábitos de riesgo en este registro.')"
                        />

                        <div class="mt-4 space-y-4">
                            <x-clinical.text-block :label="__('Observaciones no patológicas')" :content="$notPathologicalObsText" />
                            <x-clinical.text-block :label="__('Observaciones adicionales')" :content="$habitsExtraText" />
                        </div>
                    </x-glass-panel>
                </div>

                {{-- Quirúrgicos --}}
                <div
                    x-show="tab === 'quirurgicos'"
                    x-cloak
                    role="tabpanel"
                    id="panel-quirurgicos"
                    aria-labelledby="tab-quirurgicos"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes quirúrgicos') }}</h2>
                            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                                {{ __('Cirugías e intervenciones registradas en tu historia clínica.') }}
                            </p>
                        </div>

                        @if ($surgicalCount === 0)
                            <x-portal.empty-state
                                class="!py-8"
                                :title="__('Sin antecedentes quirúrgicos')"
                                :description="__('No hay cirugías documentadas en tu historial actual.')"
                            >
                                <x-slot name="icon">
                                    <flux:icon.scissors class="size-7 text-portal-primary dark:text-portal-primary" />
                                </x-slot>
                            </x-portal.empty-state>
                        @else
                            <div class="space-y-4">
                                <x-clinical.text-block :label="__('Cirugías')" :content="$surgicalText" />
                                <x-clinical.text-block :label="__('Observaciones adicionales')" :content="$surgicalExtraText" />
                            </div>
                        @endif
                    </x-glass-panel>
                </div>

                {{-- Alergias --}}
                <div
                    x-show="tab === 'alergias'"
                    x-cloak
                    role="tabpanel"
                    id="panel-alergias"
                    aria-labelledby="tab-alergias"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Alergias') }}</h2>
                            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                                {{ __('Revisa esta sección con atención: ayuda a prevenir riesgos en tratamientos futuros.') }}
                            </p>
                        </div>

                        @if ($allergyCount === 0)
                            <x-portal.empty-state
                                class="!py-8"
                                :title="__('Sin alergias registradas')"
                                :description="__('No hay alergias documentadas en tu historial actual.')"
                            >
                                <x-slot name="icon">
                                    <flux:icon.shield-check class="size-7 text-portal-primary dark:text-portal-primary" />
                                </x-slot>
                            </x-portal.empty-state>
                        @else
                            <div class="space-y-4">
                                <x-clinical.text-block :label="__('Alergias')" :content="$allergiesText" />
                                <x-clinical.text-block :label="__('Observaciones')" :content="$allergiesObsText" />
                            </div>
                        @endif
                    </x-glass-panel>
                </div>

                {{-- Medicamentos --}}
                <div
                    x-show="tab === 'medicamentos'"
                    x-cloak
                    role="tabpanel"
                    id="panel-medicamentos"
                    aria-labelledby="tab-medicamentos"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Medicamentos') }}</h2>
                            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                                {{ __('Medicamentos, suplementos y observaciones relacionadas.') }}
                            </p>
                        </div>

                        @if ($medicationCount === 0)
                            <x-portal.empty-state
                                class="!py-8"
                                :title="__('Sin medicamentos registrados')"
                                :description="__('No hay medicamentos ni suplementos documentados en tu historial actual.')"
                            >
                                <x-slot name="icon">
                                    <flux:icon.archive-box class="size-7 text-portal-primary dark:text-portal-primary" />
                                </x-slot>
                            </x-portal.empty-state>
                        @else
                            <div class="space-y-4">
                                <x-clinical.text-block :label="__('Medicamentos y suplementos')" :content="$medicationsText" />
                                <x-clinical.text-block :label="__('Observaciones')" :content="$medicationsObsText" />
                            </div>
                        @endif
                    </x-glass-panel>
                </div>

                {{-- Ginecológicos --}}
                <div
                    x-show="tab === 'ginecologicos'"
                    x-cloak
                    role="tabpanel"
                    id="panel-ginecologicos"
                    aria-labelledby="tab-ginecologicos"
                    class="portal-tab-panel"
                >
                    <x-glass-panel variant="pure">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes ginecológicos') }}</h2>
                            <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">{{ __('Información específica de salud ginecológica y reproductiva.') }}</p>
                        </div>

                        @if ($gynCount === 0)
                            <x-portal.empty-state
                                class="!py-8"
                                :title="__('Sin antecedentes ginecológicos')"
                                :description="__('No hay información ginecológica documentada en este registro.')"
                            >
                                <x-slot name="icon">
                                    <flux:icon.heart class="size-7 text-portal-primary dark:text-portal-primary" />
                                </x-slot>
                            </x-portal.empty-state>
                        @else
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @if (filled($history->edad_primera_menstruation))
                                    <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                        <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Edad primera menstruación') }}</p>
                                        <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->edad_primera_menstruation }}</p>
                                    </div>
                                @endif
                                @if (filled($history->fecha_ultima_regla))
                                    <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                        <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Fecha última regla') }}</p>
                                        <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->fecha_ultima_regla }}</p>
                                    </div>
                                @endif
                                @if (filled($history->numero_embarazos))
                                    <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                        <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Embarazos') }}</p>
                                        <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->numero_embarazos }}</p>
                                    </div>
                                @endif
                                @if (filled($history->numero_partos))
                                    <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                        <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Partos') }}</p>
                                        <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->numero_partos }}</p>
                                    </div>
                                @endif
                                @if (filled($history->numero_abortos))
                                    <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                        <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Abortos') }}</p>
                                        <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->numero_abortos }}</p>
                                    </div>
                                @endif
                                @if (filled($history->cesareas))
                                    <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/80">
                                        <p class="text-xs font-semibold uppercase text-portal-muted dark:text-zinc-400">{{ __('Cesáreas') }}</p>
                                        <p class="mt-1 text-sm font-medium text-portal-ink dark:text-zinc-100">{{ $history->cesareas }}</p>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-4 space-y-4">
                                <x-clinical.text-block :label="__('Observaciones ginecológicas')" :content="$gynObsText" />
                                <x-clinical.text-block :label="__('Observaciones adicionales')" :content="$gynExtraText" />
                            </div>
                        @endif
                    </x-glass-panel>
                </div>
            </div>
        @endif

        <p class="text-center text-xs leading-relaxed text-portal-muted dark:text-zinc-500">
            {{ __('La información es orientativa y no sustituye la consulta médica. Ante urgencias, acude a servicios de emergencia.') }}
        </p>
    </div>
</x-layouts.app>
