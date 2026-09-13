<x-layouts.app :title="__('Documentos')">
    @php
    $qcContactChannels = \App\Http\Requests\StoreCaseQualitySurveyRequest::CONTACT_CHANNELS;
    $qcServices = \App\Http\Requests\StoreCaseQualitySurveyRequest::SERVICES;
    $qcSatisfaction = \App\Http\Requests\StoreCaseQualitySurveyRequest::SATISFACTION;
    $qcDefaults = $qualitySurveyDefaults ?? [
        'patient_identity_card' => '',
        'patient_full_name' => '',
    ];

    $searchPayload = [
        'total' => $summary['documents'] ?? 0,
        'month_labels' => collect($filterOptions['months'] ?? [])->pluck('label', 'value'),
        'quality_survey_complete_url_template' => route('cases.quality-survey.complete', ['case' => '__CASE__']),
        'quality_survey_defaults' => [
            'patient_identity_card' => (string) ($qcDefaults['patient_identity_card'] ?? ''),
            'patient_full_name' => (string) ($qcDefaults['patient_full_name'] ?? ''),
        ],
        'quality_survey_options' => [
            'contact_channels' => $qcContactChannels,
            'services' => $qcServices,
            'satisfaction' => $qcSatisfaction,
        ],
        'cases' => ($cases ?? collect())->map(fn (array $case): array => [
            'id' => $case['id'],
            'code' => $case['code'] ?? null,
            'doctors' => $case['doctors'],
            'services' => $case['services'],
            'reference_date_key' => (string) ($case['reference_date_key'] ?? ''),
            'quality_survey_completed' => (bool) ($case['quality_survey_completed'] ?? false),
            'documents' => collect($case['documents'] ?? [])->map(fn (array $document): array => [
                'uid' => $document['uid'] ?? md5(($document['source'] ?? '').'|'.($document['source_id'] ?? '').'|'.($document['file_path'] ?? '')),
                'document_name' => (string) ($document['document_name'] ?? ''),
                'file_name' => basename((string) ($document['file_path'] ?? $document['document_name'] ?? '')),
                'date_key' => $document['date_key'] ?? '',
            ])->values()->all(),
        ])->values()->all(),
        'general' => collect($generalDocuments ?? [])->map(fn (array $document): array => [
            'uid' => $document['uid'] ?? md5(($document['source'] ?? '').'|'.($document['source_id'] ?? '').'|'.($document['file_path'] ?? '')),
            'document_name' => (string) ($document['document_name'] ?? ''),
            'file_name' => basename((string) ($document['file_path'] ?? $document['document_name'] ?? '')),
            'date_key' => $document['date_key'] ?? '',
        ])->values()->all(),
    ];
    @endphp

    <div x-data="portalDocumentsSearch(@js($searchPayload))" class="portal-stack-appear portal-typography-fluid mx-auto flex w-full max-w-[1220px] flex-col gap-6 motion-safe:animate-portal-fade xl:gap-8">
        <header class="flex flex-col gap-4 border-b border-portal-border-soft py-6 dark:border-zinc-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span class="mt-0.5 flex size-12 shrink-0 items-center justify-center rounded-2xl bg-portal-primary-soft text-portal-primary ring-1 ring-portal-primary/25 dark:bg-portal-primary-soft dark:text-portal-primary" aria-hidden="true">
                        <flux:icon name="folder-open" class="size-7" />
                    </span>
                    <div>
                        <flux:heading size="xl" class="font-semibold tracking-tight text-portal-ink dark:text-zinc-100">
                            {{ __('Mis documentos') }}
                        </flux:heading>
                        <flux:subheading class="mt-1 max-w-3xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                            {{ __('Encuentra cualquier archivo por fecha o nombre. Todo está pensado para que lo uses sin complicaciones.') }}
                        </flux:subheading>
                    </div>
                </div>

                @if ($isPatient)
                <flux:modal.trigger name="upload-patient-document">
                    <flux:button variant="primary" icon="arrow-up-tray" class="portal-glass-button" data-test="upload-document-button">
                        {{ __('Cargar documento') }}
                    </flux:button>
                </flux:modal.trigger>
                @endif
            </div>
        </header>

        @if (session('portal.upload_success'))
        <x-portal.alert variant="success" :title="__('Documento cargado')">
            {{ session('portal.upload_success') }}
        </x-portal.alert>
        @endif

        @if (! $isPatient)
        <x-portal.alert variant="warning" :title="__('Cuenta no vinculada a paciente')">
            {{ __('Esta vista solo está disponible para cuentas de pacientes autenticadas desde la base clínica.') }}
        </x-portal.alert>
        @else
        @php
            $casesCollection = collect($cases);
            $hasCases = $casesCollection->isNotEmpty();
            $hasAnyDocuments = (int) ($summary['documents'] ?? 0) > 0;
        @endphp

        @if ($hasAnyDocuments)
            <x-portal.documents-search :filter-options="$filterOptions" :total-documents="$summary['documents']" />
        @endif

        @if (! $hasCases && ! $hasAnyDocuments)
            <x-portal.empty-state
                class="!py-10"
                :title="__('Todavía no hay casos ni documentos')"
                :description="__('Cuando tengas atenciones registradas verás aquí todos tus casos. También puedes cargar un documento con el botón superior.')"
            >
                <x-slot name="icon">
                    <flux:icon name="document-text" class="size-7 text-portal-primary dark:text-portal-primary" />
                </x-slot>
                <x-slot name="actions">
                    <flux:modal.trigger name="upload-patient-document">
                        <flux:button variant="primary" icon="arrow-up-tray" class="portal-glass-button">{{ __('Cargar documento') }}</flux:button>
                    </flux:modal.trigger>
                </x-slot>
            </x-portal.empty-state>
        @else
            <div x-show="hasActiveFilters() && visibleCount() === 0 && {{ $hasAnyDocuments ? 'true' : 'false' }}" x-cloak>
                <x-portal.empty-state
                    class="!py-8"
                    :title="__('No encontramos coincidencias')"
                    :description="__('Prueba con otra fecha o una palabra diferente. También puedes limpiar la búsqueda para ver todo de nuevo.')"
                >
                    <x-slot name="icon">
                        <flux:icon name="magnifying-glass" class="size-7 text-portal-primary dark:text-portal-primary" />
                    </x-slot>
                    <x-slot name="actions">
                        <flux:button variant="primary" type="button" @click="clearFilters()">
                            {{ __('Ver todos los documentos') }}
                        </flux:button>
                    </x-slot>
                </x-portal.empty-state>
            </div>

            <div class="space-y-4">
                @if ($hasCases)
                    <p class="text-sm text-portal-muted dark:text-zinc-400">
                        {{ __('Toca una fila para ver u ocultar los documentos del caso.') }}
                    </p>
                @endif

                <div x-show="isGeneralSectionVisible()" x-cloak>
                    <x-glass-panel variant="pure" class="portal-case-row-panel overflow-hidden p-0">
                        <x-portal.collapsible-case-section
                            case-key="general"
                            :default-open="false"
                            :document-count="count($generalDocuments)"
                            :hide-doc-label="true"
                        >
                            <x-slot:header>
                                <div class="portal-case-row portal-case-row--general">
                                    <span class="portal-case-row__rank" aria-hidden="true">G</span>
                                    <div class="portal-case-row__cell portal-case-row__cell--case">
                                        <span class="portal-case-row__value">{{ __('Documentos generales') }}</span>
                                        <span class="portal-case-row__hint sm:hidden">{{ __('Sin caso vinculado') }}</span>
                                    </div>
                                    <div class="portal-case-row__cell portal-case-row__cell--status">
                                        <span class="portal-case-row__pill portal-case-row__pill--muted">{{ __('General') }}</span>
                                    </div>
                                    <div class="portal-case-row__cell portal-case-row__cell--date">
                                        <span class="portal-case-row__value">—</span>
                                    </div>
                                    <div class="portal-case-row__cell portal-case-row__cell--doctor">
                                        <span class="portal-case-row__value">{{ __('Tu carga') }}</span>
                                    </div>
                                    <div class="portal-case-row__cell portal-case-row__cell--reason">
                                        <span class="portal-case-row__value portal-case-row__value--muted">
                                            {{ __('Archivos sin vincular a un caso clínico') }}
                                        </span>
                                    </div>
                                    @php $generalDocCount = count($generalDocuments); @endphp
                                    <div class="portal-case-row__cell portal-case-row__cell--docs">
                                        <div class="portal-case-row__docs">
                                            <span class="portal-case-row__docs-label">
                                                {{ trans_choice(':count documento|:count documentos', $generalDocCount, ['count' => $generalDocCount]) }}
                                            </span>
                                            <div class="portal-case-row__bar" aria-hidden="true">
                                                <span style="--portal-case-docs: {{ min(100, $generalDocCount * 20) }}%"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </x-slot:header>

                            @foreach ($generalDocuments as $document)
                                <div role="listitem" x-show="isDocumentVisible(@js($document['uid']), null)" x-cloak>
                                    <x-portal.document-card :document="$document" />
                                </div>
                            @endforeach
                        </x-portal.collapsible-case-section>
                    </x-glass-panel>
                </div>

                @if ($hasCases)
                    <div class="portal-case-table">
                        <div class="portal-case-table__head" aria-hidden="true">
                            <span>#</span>
                            <span>{{ __('Caso') }}</span>
                            <span>{{ __('Estado') }}</span>
                            <span>{{ __('Fecha') }}</span>
                            <span>{{ __('Médico') }}</span>
                            <span>{{ __('Motivo') }}</span>
                            <span>{{ __('Documentos') }}</span>
                            <span></span>
                        </div>

                        <div class="portal-case-table__body space-y-1.5">
                            @foreach ($casesCollection as $case)
                                @php
                                    $statusKey = str($case['status'] ?? '')->lower()->ascii()->toString();
                                    $statusTone = match (true) {
                                        str_contains($statusKey, 'alta') => 'success',
                                        str_contains($statusKey, 'proceso'), str_contains($statusKey, 'activo'), str_contains($statusKey, 'abierto') => 'info',
                                        str_contains($statusKey, 'cerrado'), str_contains($statusKey, 'cancel') => 'muted',
                                        str_contains($statusKey, 'pendiente'), str_contains($statusKey, 'espera') => 'warn',
                                        default => 'info',
                                    };
                                    $docCount = (int) ($case['document_count'] ?? 0);
                                    $docFill = min(100, max($docCount > 0 ? 18 : 0, $docCount * 22));
                                @endphp
                                <div x-show="isCaseVisible({{ (int) $case['id'] }})" x-cloak>
                                    <x-glass-panel variant="pure" class="portal-case-row-panel overflow-hidden p-0">
                                        <x-portal.collapsible-case-section
                                            :case-key="(string) $case['id']"
                                            :default-open="false"
                                            :document-count="$docCount"
                                            :hide-doc-label="true"
                                        >
                                            <x-slot:header>
                                                <div class="portal-case-row">
                                                    <span class="portal-case-row__rank">{{ $loop->iteration }}</span>

                                                    <div class="portal-case-row__cell portal-case-row__cell--case">
                                                        <span class="portal-case-row__label sm:hidden">{{ __('Caso') }}</span>
                                                        <span class="portal-case-row__value portal-case-row__value--strong font-mono">
                                                            {{ $case['code'] ?: '—' }}
                                                        </span>
                                                    </div>

                                                    <div class="portal-case-row__cell portal-case-row__cell--status">
                                                        <span class="portal-case-row__label sm:hidden">{{ __('Estado') }}</span>
                                                        <span @class(['portal-case-row__pill', 'portal-case-row__pill--'.$statusTone])>
                                                            {{ filled($case['status']) ? $case['status'] : __('Sin estado') }}
                                                        </span>
                                                    </div>

                                                    <div class="portal-case-row__cell portal-case-row__cell--date">
                                                        <span class="portal-case-row__label sm:hidden">{{ __('Fecha') }}</span>
                                                        <span class="portal-case-row__value">{{ $case['reference_date_label'] ?: '—' }}</span>
                                                    </div>

                                                    <div class="portal-case-row__cell portal-case-row__cell--doctor">
                                                        <span class="portal-case-row__label sm:hidden">{{ __('Médico') }}</span>
                                                        <span class="portal-case-row__value" title="{{ $case['doctors_label'] }}">
                                                            {{ $case['doctors_label'] ?: '—' }}
                                                        </span>
                                                    </div>

                                                    <div class="portal-case-row__cell portal-case-row__cell--reason">
                                                        <span class="portal-case-row__label sm:hidden">{{ __('Motivo') }}</span>
                                                        <span class="portal-case-row__value" title="{{ filled($case['reason']) ? $case['reason'] : '' }}">
                                                            {{ filled($case['reason']) ? $case['reason'] : __('Sin motivo registrado') }}
                                                        </span>
                                                    </div>

                                                    <div class="portal-case-row__cell portal-case-row__cell--docs">
                                                        <span class="portal-case-row__label sm:hidden">{{ __('Documentos') }}</span>
                                                        <div class="portal-case-row__docs">
                                                            <span class="portal-case-row__docs-label">
                                                                {{ trans_choice(':count doc.|:count docs.', $docCount, ['count' => $docCount]) }}
                                                            </span>
                                                            <div class="portal-case-row__bar" aria-hidden="true">
                                                                <span style="--portal-case-docs: {{ $docFill }}%"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </x-slot:header>

                                            @forelse ($case['documents'] as $document)
                                                <div role="listitem" x-show="isDocumentVisible(@js($document['uid']), {{ $case['id'] }})" x-cloak>
                                                    <x-portal.document-card :document="$document" />
                                                </div>
                                            @empty
                                                <div class="portal-doc-empty" role="listitem">
                                                    <p>{{ __('Este caso aún no tiene documentos asociados.') }}</p>
                                                </div>
                                            @endforelse
                                        </x-portal.collapsible-case-section>
                                    </x-glass-panel>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
        @endif

        @if ($isPatient)
        <flux:modal name="upload-patient-document" :show="$errors->any()" focusable class="max-w-xl">
            <form method="POST" action="{{ route('cases.documents.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <flux:heading size="lg">{{ __('Cargar documento') }}</flux:heading>
                    <flux:subheading class="mt-1">
                        {{ __('Describe el archivo con un nombre y una razón clara. El caso es opcional: déjalo vacío si el documento no está ligado a una atención con nosotros.') }}
                    </flux:subheading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Caso (opcional)') }}</flux:label>
                    <flux:select name="telemedicine_case_id">
                        <option value="">{{ __('Sin caso — documento general / externo') }}</option>
                        @foreach ($patientCases as $caseOption)
                        <option value="{{ $caseOption['id'] }}" @selected((int) old('telemedicine_case_id')===(int) $caseOption['id'])>
                            {{ $caseOption['label'] ?? $caseOption['code'] }}
                        </option>
                        @endforeach
                    </flux:select>
                    <flux:description>
                        {{ __('Si lo asocias a un caso, verás fecha, doctor y servicio para encontrarlo más rápido después.') }}
                    </flux:description>
                    <flux:error name="telemedicine_case_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nombre del documento') }}</flux:label>
                    <flux:input name="document_name" :value="old('document_name')" placeholder="{{ __('Ej.: Resultado de laboratorio — hemograma') }}" required />
                    <flux:description>{{ __('Usa un nombre corto y claro para identificar el archivo.') }}</flux:description>
                    <flux:error name="document_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Razón de la carga') }}</flux:label>
                    <flux:textarea name="upload_reason" rows="4" placeholder="{{ __('Ej.: Estudio de otro centro que quiero tener disponible para futuras consultas.') }}" required>{{ old('upload_reason') }}</flux:textarea>
                    <flux:description>
                        {{ __('Explica brevemente por qué guardas este documento. Esta nota te ayudará a recordar su propósito cuando lo necesites meses después.') }}
                    </flux:description>
                    <flux:error name="upload_reason" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Archivo') }}</flux:label>
                    <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx" required class="block w-full cursor-pointer rounded-xl border border-portal-border-soft bg-portal-surface px-3 py-2.5 text-sm text-portal-ink file:me-3 file:rounded-lg file:border-0 file:bg-portal-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-portal-primary dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:file:bg-portal-primary-soft dark:file:text-portal-primary" />
                    <flux:description>{{ __('PDF, imágenes o Word. Máximo 10 MB.') }}</flux:description>
                    <flux:error name="document_file" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancelar') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" icon="arrow-up-tray" data-test="submit-upload-document">
                        {{ __('Guardar documento') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>

            {{-- Modal Quality Control (formulario in-app obligatorio por caso) --}}
            <template x-teleport="body">
                <div
                    class="portal-qc-survey"
                    x-show="qcOpen"
                    x-cloak
                    style="display: none;"
                    @keydown.escape.window="closeQualitySurvey()"
                >
                    <div
                        class="portal-qc-survey__backdrop"
                        x-show="qcOpen"
                        x-transition.opacity.duration.200ms
                        @click="closeQualitySurvey()"
                    ></div>

                    <div
                        class="portal-qc-survey__sheet portal-qc-survey__sheet--form"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="portal-qc-survey-title"
                        x-show="qcOpen"
                        x-transition:enter="portal-qc-survey-enter"
                        x-transition:enter-start="portal-qc-survey__sheet--enter"
                        x-transition:enter-end="portal-qc-survey__sheet--entered"
                        x-transition:leave="portal-qc-survey-leave"
                        x-transition:leave-start="portal-qc-survey__sheet--entered"
                        x-transition:leave-end="portal-qc-survey__sheet--enter"
                        @click.stop
                    >
                        <div class="portal-qc-survey__handle" aria-hidden="true"></div>

                        <header class="portal-qc-survey__header">
                            <div>
                                <p class="portal-qc-survey__eyebrow">{{ __('Control de calidad') }}</p>
                                <h3 id="portal-qc-survey-title" class="portal-qc-survey__title">
                                    {{ __('Cuestionario del servicio') }}
                                </h3>
                            </div>
                            <button type="button" class="portal-qc-survey__close" @click="closeQualitySurvey()" aria-label="{{ __('Cerrar') }}">
                                <flux:icon name="x-mark" class="size-5" />
                            </button>
                        </header>

                        <form class="portal-qc-survey__form" @submit.prevent="submitQualitySurvey()">
                            <p class="portal-qc-survey__copy">
                                {{ __('Responde este cuestionario una sola vez por caso. Es obligatorio para ver y descargar los documentos.') }}
                            </p>

                            <div class="portal-qc-survey__case" x-show="qcCase">
                                <p class="portal-qc-survey__case-label">{{ __('Caso') }}</p>
                                <p class="portal-qc-survey__case-code font-mono" x-text="qcCase?.code || ('#' + (qcCase?.id || ''))"></p>
                            </div>

                            <div class="portal-qc-survey__fields">
                                <label class="portal-qc-survey__field">
                                    <span>{{ __('Fecha del servicio') }}</span>
                                    <input type="date" x-model="qcForm.service_date" required>
                                </label>

                                <label class="portal-qc-survey__field">
                                    <span>{{ __('Cédula del paciente') }}</span>
                                    <input type="text" x-model="qcForm.patient_identity_card" autocomplete="off" required maxlength="30">
                                </label>

                                <label class="portal-qc-survey__field">
                                    <span>{{ __('Nombre completo') }}</span>
                                    <input type="text" x-model="qcForm.patient_full_name" autocomplete="name" required maxlength="160">
                                </label>

                                <fieldset class="portal-qc-survey__fieldset">
                                    <legend>{{ __('Canal por el que te contactamos') }}</legend>
                                    <template x-for="option in (payload.quality_survey_options?.contact_channels || [])" :key="'contact-' + option">
                                        <label class="portal-qc-survey__choice">
                                            <input type="radio" name="qc_contact_channel" :value="option" x-model="qcForm.contact_channel" required>
                                            <span x-text="option === 'Other' ? '{{ __('Otro') }}' : option"></span>
                                        </label>
                                    </template>
                                    <label class="portal-qc-survey__field" x-show="qcForm.contact_channel === 'Other'" x-cloak>
                                        <span>{{ __('Especifica el canal') }}</span>
                                        <input type="text" x-model="qcForm.contact_channel_other" maxlength="120" :required="qcForm.contact_channel === 'Other'">
                                    </label>
                                </fieldset>

                                <label class="portal-qc-survey__field">
                                    <span>{{ __('Servicio recibido') }}</span>
                                    <select x-model="qcForm.service_received" required>
                                        <option value="">{{ __('Selecciona una opción') }}</option>
                                        <template x-for="option in (payload.quality_survey_options?.services || [])" :key="'service-' + option">
                                            <option :value="option" x-text="option === 'Other' ? '{{ __('Otro') }}' : option"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="portal-qc-survey__field" x-show="qcForm.service_received === 'Other'" x-cloak>
                                    <span>{{ __('Especifica el servicio') }}</span>
                                    <input type="text" x-model="qcForm.service_received_other" maxlength="120" :required="qcForm.service_received === 'Other'">
                                </label>

                                <template x-for="rating in qcRatingFields" :key="rating.key">
                                    <fieldset class="portal-qc-survey__fieldset">
                                        <legend x-text="rating.label"></legend>
                                        <template x-for="option in (payload.quality_survey_options?.satisfaction || [])" :key="rating.key + '-' + option">
                                            <label class="portal-qc-survey__choice">
                                                <input
                                                    type="radio"
                                                    :name="'qc_' + rating.key"
                                                    :value="option"
                                                    :checked="qcForm[rating.key] === option"
                                                    @change="qcForm[rating.key] = option"
                                                    required
                                                >
                                                <span x-text="option"></span>
                                            </label>
                                        </template>
                                    </fieldset>
                                </template>

                                <label class="portal-qc-survey__field">
                                    <span>{{ __('Sugerencias (opcional)') }}</span>
                                    <textarea x-model="qcForm.suggestions" rows="3" maxlength="2000" placeholder="{{ __('Cuéntanos cómo podemos mejorar') }}"></textarea>
                                </label>
                            </div>

                            <p class="portal-qc-survey__error" x-show="qcError" x-text="qcError" x-cloak></p>

                            <div class="portal-qc-survey__actions">
                                <button
                                    type="submit"
                                    class="portal-qc-survey__confirm"
                                    :disabled="qcSubmitting"
                                >
                                    <span x-show="!qcSubmitting">{{ __('Enviar y ver documentos') }}</span>
                                    <span x-show="qcSubmitting" x-cloak>{{ __('Guardando…') }}</span>
                                </button>

                                <button type="button" class="portal-qc-survey__cancel" @click="closeQualitySurvey()">
                                    {{ __('Cancelar') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        @endif
    </div>

    <script>
        window.portalDocumentsSearch = function portalDocumentsSearch(payload) {
            return {
                payload,
                query: '',
                dateFrom: '',
                dateTo: '',
                year: '',
                month: '',
                dateFiltersOpen: false,
                openCaseIds: {},
                qcOpen: false,
                qcCase: null,
                qcError: '',
                qcSubmitting: false,
                qcForm: {
                    service_date: '',
                    patient_identity_card: '',
                    patient_full_name: '',
                    contact_channel: '',
                    contact_channel_other: '',
                    service_received: '',
                    service_received_other: '',
                    rating_attention_channels: '',
                    rating_response_time: '',
                    rating_medical_service: '',
                    rating_overall_satisfaction: '',
                    suggestions: '',
                },
                qcRatingFields: [
                    { key: 'rating_attention_channels', label: '{{ __('Calificación de los canales de atención') }}' },
                    { key: 'rating_response_time', label: '{{ __('Calificación del tiempo de respuesta') }}' },
                    { key: 'rating_medical_service', label: '{{ __('Calificación del servicio médico') }}' },
                    { key: 'rating_overall_satisfaction', label: '{{ __('Satisfacción general') }}' },
                ],

                init() {
                    ['query', 'dateFrom', 'dateTo', 'year', 'month'].forEach((key) => {
                        this.$watch(key, () => {
                            this.openCaseIds = {};
                        });
                    });
                },

                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        || document.querySelector('input[name="_token"]')?.value
                        || '';
                },

                caseById(caseId) {
                    const id = Number(caseId);
                    return (this.payload.cases || []).find((item) => Number(item.id) === id) || null;
                },

                isQualitySurveyCompleted(caseKey) {
                    if (String(caseKey) === 'general') {
                        return true;
                    }

                    const caseData = this.caseById(caseKey);
                    return Boolean(caseData?.quality_survey_completed);
                },

                blankQualityForm() {
                    const defaults = this.payload.quality_survey_defaults || {};

                    return {
                        service_date: '',
                        patient_identity_card: defaults.patient_identity_card || '',
                        patient_full_name: defaults.patient_full_name || '',
                        contact_channel: '',
                        contact_channel_other: '',
                        service_received: '',
                        service_received_other: '',
                        rating_attention_channels: '',
                        rating_response_time: '',
                        rating_medical_service: '',
                        rating_overall_satisfaction: '',
                        suggestions: '',
                    };
                },

                matchServiceOption(services) {
                    const options = this.payload.quality_survey_options?.services || [];
                    const values = Array.isArray(services) ? services : [];

                    for (const service of values) {
                        const normalized = this.normalize(service);
                        const match = options.find((option) => this.normalize(option) === normalized);
                        if (match) {
                            return match;
                        }
                    }

                    return '';
                },

                openQualitySurvey(caseKey) {
                    this.qcCase = this.caseById(caseKey);
                    this.qcError = '';
                    this.qcSubmitting = false;
                    this.qcForm = this.blankQualityForm();
                    this.qcForm.service_date = this.qcCase?.reference_date_key || '';
                    this.qcForm.service_received = this.matchServiceOption(this.qcCase?.services || []);
                    this.qcOpen = true;
                    document.documentElement.classList.add('portal-qc-survey-lock');
                },

                closeQualitySurvey() {
                    // El teclado virtual deja el viewport visual desplazado: si
                    // no se cierra el foco y se devuelve el paneo horizontal a
                    // cero, al volver a Documentos la lista aparece cortada por
                    // la derecha aunque el layout sea correcto.
                    if (document.activeElement instanceof HTMLElement) {
                        document.activeElement.blur();
                    }

                    this.qcOpen = false;
                    document.documentElement.classList.remove('portal-qc-survey-lock');
                    window.requestAnimationFrame(() => window.scrollTo(0, window.scrollY));
                    window.setTimeout(() => {
                        if (!this.qcOpen) {
                            this.qcCase = null;
                            this.qcError = '';
                            this.qcSubmitting = false;
                            this.qcForm = this.blankQualityForm();
                        }
                    }, 220);
                },

                async submitQualitySurvey() {
                    if (!this.qcCase?.id || this.qcSubmitting) {
                        return;
                    }

                    this.qcSubmitting = true;
                    this.qcError = '';

                    const template = this.payload.quality_survey_complete_url_template || '';
                    const url = template.replace('__CASE__', String(this.qcCase.id));

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                service_date: this.qcForm.service_date,
                                patient_identity_card: this.qcForm.patient_identity_card,
                                patient_full_name: this.qcForm.patient_full_name,
                                contact_channel: this.qcForm.contact_channel,
                                contact_channel_other: this.qcForm.contact_channel_other || null,
                                service_received: this.qcForm.service_received,
                                service_received_other: this.qcForm.service_received_other || null,
                                rating_attention_channels: this.qcForm.rating_attention_channels,
                                rating_response_time: this.qcForm.rating_response_time,
                                rating_medical_service: this.qcForm.rating_medical_service,
                                rating_overall_satisfaction: this.qcForm.rating_overall_satisfaction,
                                suggestions: this.qcForm.suggestions || null,
                            }),
                        });

                        // Sesión o token caducados: recargar es lo único que
                        // devuelve un token válido, y así el usuario no ve un
                        // error técnico que no puede resolver.
                        if (response.status === 419 || response.status === 401) {
                            this.qcError = '{{ __('Tu sesión expiró. Recargando…') }}';
                            window.location.reload();

                            return;
                        }

                        const data = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            const firstError = data.errors
                                ? Object.values(data.errors).flat()[0]
                                : null;
                            throw new Error(firstError || data.message || '{{ __('No se pudo registrar el cuestionario.') }}');
                        }

                        this.payload.cases = (this.payload.cases || []).map((item) => {
                            if (Number(item.id) !== Number(this.qcCase.id)) {
                                return item;
                            }

                            return {
                                ...item,
                                quality_survey_completed: true,
                            };
                        });

                        const caseId = String(this.qcCase.id);
                        this.closeQualitySurvey();
                        this.openCaseIds = {
                            ...this.openCaseIds,
                            [caseId]: true,
                        };
                    } catch (error) {
                        this.qcError = error?.message || '{{ __('No se pudo registrar el cuestionario.') }}';
                        this.qcSubmitting = false;
                    }
                },

                normalize(value) {
                    return (value || '')
                        .toString()
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[_.-]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                },

                hasDateFilters() {
                    return !!(this.dateFrom || this.dateTo || this.year || this.month);
                },

                activeDateFilterCount() {
                    return [this.dateFrom, this.dateTo, this.year, this.month]
                        .filter((value) => !!(value && String(value).trim()))
                        .length;
                },

                dateRangeInvalid() {
                    return !!(this.dateFrom && this.dateTo && this.dateFrom > this.dateTo);
                },

                dateMatches(dateKey) {
                    if (!this.hasDateFilters()) {
                        return true;
                    }

                    if (!dateKey) {
                        return false;
                    }

                    if (this.dateRangeInvalid()) {
                        return false;
                    }

                    if (this.dateFrom && dateKey < this.dateFrom) {
                        return false;
                    }

                    if (this.dateTo && dateKey > this.dateTo) {
                        return false;
                    }

                    if (this.year && !dateKey.startsWith(`${this.year}-`)) {
                        return false;
                    }

                    if (this.month && dateKey.substring(5, 7) !== this.month) {
                        return false;
                    }

                    return true;
                },

                documentMatchesText(doc) {
                    const q = this.normalize(this.query);

                    if (!q) {
                        return true;
                    }

                    const name = this.normalize(doc.document_name || '');
                    const fileName = this.normalize(
                        (doc.file_name || '').replace(/\.[^.]+$/, '')
                    );
                    const haystack = `${name} ${fileName}`.trim();

                    // Todas las palabras deben aparecer en el nombre del documento.
                    return q.split(/\s+/).filter(Boolean).every((token) => haystack.includes(token));
                },

                documentMatchesFilters(doc) {
                    if (!this.documentMatchesText(doc)) {
                        return false;
                    }

                    if (!this.dateMatches(doc.date_key)) {
                        return false;
                    }

                    return true;
                },

                findDocument(uid, caseId) {
                    if (caseId === null || caseId === 'general') {
                        return this.payload.general.find((item) => item.uid === uid) || null;
                    }

                    const caseData = this.payload.cases.find((item) => Number(item.id) === Number(caseId));

                    if (!caseData) {
                        return null;
                    }

                    return caseData.documents.find((item) => item.uid === uid) || null;
                },

                isDocumentVisible(uid, caseId) {
                    if (this.dateRangeInvalid()) {
                        return false;
                    }

                    const doc = this.findDocument(uid, caseId);

                    return !!doc && this.documentMatchesFilters(doc);
                },

                caseHasMatchingDocuments(caseId) {
                    const caseData = this.payload.cases.find((item) => Number(item.id) === Number(caseId));

                    if (!caseData) {
                        return false;
                    }

                    return caseData.documents.some((doc) => this.documentMatchesFilters(doc));
                },

                isCaseVisible(caseId) {
                    if (!this.payload.cases.some((item) => Number(item.id) === Number(caseId))) {
                        return false;
                    }

                    if (!this.hasActiveFilters()) {
                        return true;
                    }

                    if (this.dateRangeInvalid()) {
                        return false;
                    }

                    return this.caseHasMatchingDocuments(caseId);
                },

                isGeneralSectionVisible() {
                    if (!this.payload.general.length) {
                        return false;
                    }

                    if (this.dateRangeInvalid()) {
                        return false;
                    }

                    if (!this.hasActiveFilters()) {
                        return true;
                    }

                    return this.payload.general.some((doc) => this.documentMatchesFilters(doc));
                },

                isCaseOpen(caseKey) {
                    const key = String(caseKey);
                    // Leer la clave siempre: Alpine solo re-renderiza si se trackeó openCaseIds[key].
                    const manual = this.openCaseIds[key];

                    if (manual !== undefined) {
                        return !!manual;
                    }

                    // Sin preferencia manual: con búsqueda activa se abren los casos con coincidencias
                    // solo si el Quality Control del caso ya está completo (excepto generales).
                    if (this.hasActiveFilters() && !this.dateRangeInvalid()) {
                        if (key === 'general') {
                            return this.isGeneralSectionVisible();
                        }

                        if (!this.isQualitySurveyCompleted(key)) {
                            return false;
                        }

                        return this.caseHasMatchingDocuments(key);
                    }

                    return false;
                },

                toggleCaseOpen(caseKey) {
                    const key = String(caseKey);
                    const next = !this.isCaseOpen(key);

                    if (next && key !== 'general' && !this.isQualitySurveyCompleted(key)) {
                        this.openQualitySurvey(key);
                        return;
                    }

                    // Reasignar el objeto para garantizar reactividad en Alpine.
                    this.openCaseIds = {
                        ...this.openCaseIds,
                        [key]: next,
                    };
                },

                visibleCount() {
                    if (this.dateRangeInvalid()) {
                        return 0;
                    }

                    let count = 0;

                    this.payload.cases.forEach((caseData) => {
                        caseData.documents.forEach((doc) => {
                            if (this.isDocumentVisible(doc.uid, caseData.id)) {
                                count++;
                            }
                        });
                    });

                    this.payload.general.forEach((doc) => {
                        if (this.isDocumentVisible(doc.uid, null)) {
                            count++;
                        }
                    });

                    return count;
                },

                hasActiveFilters() {
                    return !!(
                        this.query.trim() ||
                        this.dateFrom ||
                        this.dateTo ||
                        this.year ||
                        this.month
                    );
                },

                clearFilters() {
                    this.query = '';
                    this.dateFrom = '';
                    this.dateTo = '';
                    this.year = '';
                    this.month = '';
                    this.openCaseIds = {};
                },

                activeFiltersLabel() {
                    const parts = [];

                    if (this.query.trim()) {
                        parts.push(`Palabra: “${this.query.trim()}”`);
                    }

                    if (this.dateFrom || this.dateTo) {
                        if (this.dateFrom && this.dateTo) {
                            parts.push(`Rango: ${this.dateFrom} → ${this.dateTo}`);
                        } else if (this.dateFrom) {
                            parts.push(`Desde: ${this.dateFrom}`);
                        } else {
                            parts.push(`Hasta: ${this.dateTo}`);
                        }
                    }

                    if (this.year) {
                        parts.push(`Año: ${this.year}`);
                    }

                    if (this.month) {
                        parts.push(`Mes: ${this.monthLabel()}`);
                    }

                    return parts.length ? `Filtros activos: ${parts.join(' · ')}` : '';
                },

                monthLabel() {
                    return this.payload.month_labels?.[this.month] || this.month;
                },

                resultsLabel() {
                    const total = this.payload.total;

                    if (this.dateRangeInvalid()) {
                        return 'Revisa el rango de fechas para ver resultados';
                    }

                    const visible = this.visibleCount();

                    if (!this.hasActiveFilters()) {
                        return `Mostrando ${total} documentos`;
                    }

                    if (visible === 0) {
                        return 'No encontramos documentos con esa búsqueda';
                    }

                    return `Encontramos ${visible} de ${total} documentos`;
                },
            };
        };

    </script>
</x-layouts.app>
