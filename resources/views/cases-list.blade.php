<x-layouts.app :title="__('Lista de casos')">
    <div class="portal-stack-appear portal-typography-fluid mx-auto flex w-full max-w-[1220px] flex-col gap-8 motion-safe:animate-portal-fade">
        <header class="flex flex-col gap-3 border-b border-portal-border-soft pb-7 dark:border-zinc-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span
                        class="mt-0.5 flex size-12 shrink-0 items-center justify-center rounded-2xl bg-portal-primary-soft text-portal-primary ring-1 ring-portal-primary/25 dark:bg-portal-primary-soft dark:text-portal-primary"
                        aria-hidden="true"
                    >
                        <flux:icon name="clipboard-document-list" class="size-7" />
                    </span>
                    <div>
                        <flux:heading size="xl" class="font-semibold tracking-tight text-portal-ink dark:text-zinc-100">
                            {{ __('Lista de casos') }}
                        </flux:heading>
                        <flux:subheading class="mt-1 max-w-3xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                            {{ __('Aquí puedes revisar cada caso con un resumen fácil de leer: qué te atendieron, qué te indicaron y qué resultados se solicitaron.') }}
                        </flux:subheading>
                    </div>
                </div>
            </div>
        </header>

        @if (! $isPatient)
            <x-portal.alert variant="warning" :title="__('Cuenta no vinculada a paciente')">
                {{ __('Esta vista solo está disponible para cuentas de pacientes autenticadas desde la base clínica.') }}
            </x-portal.alert>
        @elseif ($cases->isEmpty())
            <x-portal.empty-state
                :title="__('Aún no tienes casos registrados')"
                :description="__('Cuando inicies consultas telemédicas y se creen casos clínicos, podrás ver aquí todo el histórico organizado.')"
            >
                <x-slot name="icon">
                    <flux:icon name="folder-open" class="size-7 text-portal-primary dark:text-portal-primary" />
                </x-slot>
                <x-slot name="actions">
                    <flux:button variant="primary" :href="route('dashboard')" wire:navigate icon="home">
                        {{ __('Volver al inicio') }}
                    </flux:button>
                </x-slot>
            </x-portal.empty-state>
        @else
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <x-portal.card variant="raised" class="xl:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Casos') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-portal-ink dark:text-zinc-100">{{ $totalSummary['cases'] }}</p>
                </x-portal.card>
                <x-portal.card variant="raised" class="xl:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Consultas') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-portal-ink dark:text-zinc-100">{{ $totalSummary['consultations'] }}</p>
                </x-portal.card>
                <x-portal.card variant="raised" class="xl:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Medicamentos') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-portal-ink dark:text-zinc-100">{{ $totalSummary['medications'] }}</p>
                </x-portal.card>
                <x-portal.card variant="raised" class="xl:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Laboratorios') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-portal-ink dark:text-zinc-100">{{ $totalSummary['labs'] }}</p>
                </x-portal.card>
                <x-portal.card variant="raised" class="xl:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Estudios') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-portal-ink dark:text-zinc-100">{{ $totalSummary['studies'] }}</p>
                </x-portal.card>
                <x-portal.card variant="raised" class="xl:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Reportes') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-portal-ink dark:text-zinc-100">{{ $totalSummary['reports'] }}</p>
                </x-portal.card>
            </div>

            <div class="space-y-5">
                @foreach ($cases as $case)
                    <x-portal.card
                        variant="raised"
                        class="portal-glass-panel overflow-hidden border-portal-border-soft bg-gradient-to-br from-portal-surface via-portal-surface to-portal-subtle/45 p-0 dark:border-zinc-700 dark:from-zinc-900 dark:via-zinc-900 dark:to-zinc-800/70"
                    >
                        <div class="border-b border-portal-border-soft px-5 py-5 dark:border-zinc-700 sm:px-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2.5">
                                        <flux:badge color="sky" size="sm" class="font-mono">
                                            {{ $case['code'] }}
                                        </flux:badge>
                                        <flux:badge variant="outline" size="sm">{{ $case['status'] }}</flux:badge>
                                        @if ($case['priority'])
                                            <flux:badge color="amber" size="sm">{{ $case['priority'] }}</flux:badge>
                                        @endif
                                    </div>
                                    @if (filled($case['reason']))
                                        <p class="max-w-3xl text-sm leading-relaxed text-portal-ink dark:text-zinc-200">
                                            {{ $case['reason'] }}
                                        </p>
                                    @else
                                        <p class="text-sm text-portal-muted dark:text-zinc-400">
                                            {{ __('Sin motivo de caso registrado.') }}
                                        </p>
                                    @endif
                                </div>
                                <div class="grid min-w-[14rem] gap-1 text-right text-xs text-portal-muted dark:text-zinc-400">
                                    <span>{{ __('Actualizado:') }} {{ optional($case['updated_at'])->translatedFormat('d M Y H:i') ?? '—' }}</span>
                                    @if (filled($case['managed_by']))
                                        <span>{{ __('Gestionado por:') }} {{ $case['managed_by'] }}</span>
                                    @endif
                                    @if (filled($case['assigned_by']))
                                        <span>{{ __('Asignado por:') }} {{ $case['assigned_by'] }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/55">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Servicios principales') }}</p>
                                    <p class="mt-1.5 text-sm font-medium text-portal-ink dark:text-zinc-100">
                                        {{ $case['main_services']->isNotEmpty() ? $case['main_services']->join(', ') : __('Sin dato') }}
                                    </p>
                                </div>
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/55">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Servicios derivados') }}</p>
                                    <p class="mt-1.5 text-sm font-medium text-portal-ink dark:text-zinc-100">
                                        {{ $case['derived_services']->isNotEmpty() ? $case['derived_services']->join(', ') : __('Sin servicios derivados') }}
                                    </p>
                                </div>
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/55">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Consultas') }}</p>
                                    <p class="mt-1.5 text-2xl font-semibold text-portal-ink dark:text-zinc-100">{{ $case['summary']['consultations'] }}</p>
                                </div>
                                <div class="portal-liquid-section rounded-xl px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/55">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Resumen clínico') }}</p>
                                    <p class="mt-1.5 text-sm text-portal-ink dark:text-zinc-200">
                                        {{ __('Meds: :m | Labs: :l | Estudios: :s | Reportes: :r', ['m' => $case['summary']['medications'], 'l' => $case['summary']['labs'], 's' => $case['summary']['studies'], 'r' => $case['summary']['reports']]) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-5 sm:px-6">
                            <flux:accordion class="space-y-0">
                                <flux:accordion.item
                                    expanded
                                    class="portal-glass-panel mb-3 overflow-hidden rounded-xl border border-portal-border-soft bg-portal-surface !border-b-0 !pb-0 !pt-0 dark:border-zinc-700 dark:bg-zinc-900/70 last:mb-0"
                                >
                                    <flux:accordion.heading class="px-4 py-3.5 text-sm font-semibold text-portal-ink dark:text-zinc-100">
                                        <span class="flex items-center gap-2">
                                            <flux:icon name="clipboard-document-list" class="size-4 text-portal-primary dark:text-portal-primary" />
                                            {{ __('Histórico de consultas') }}
                                        </span>
                                    </flux:accordion.heading>

                                    <flux:accordion.content class="border-t border-portal-border-soft px-4 py-4 dark:border-zinc-700">
                                        <div class="space-y-4">
                                            @forelse ($case['consultations'] as $consultation)
                                                <div class="portal-glass-panel rounded-xl border border-portal-border-soft bg-portal-subtle/45 p-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                                                    <div class="mb-3 flex flex-wrap items-center gap-2">
                                                        <flux:badge variant="outline" size="sm">
                                                            {{ __('Consulta #:id', ['id' => $consultation['id']]) }}
                                                        </flux:badge>
                                                        <flux:badge color="sky" size="sm">{{ $consultation['status'] }}</flux:badge>
                                                        @if ($consultation['priority'])
                                                            <flux:badge color="amber" size="sm">{{ $consultation['priority'] }}</flux:badge>
                                                        @endif
                                                        @if ($consultation['date'])
                                                            <flux:badge variant="outline" size="sm">
                                                                {{ $consultation['date']->translatedFormat('d M Y H:i') }}
                                                            </flux:badge>
                                                        @endif
                                                    </div>

                                                    <div class="grid gap-4 xl:grid-cols-3">
                                                        <div class="space-y-2 xl:col-span-2">
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">
                                                                {{ __('Resumen de la consulta') }}
                                                            </p>
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">
                                                                {{ __('Servicios de la consulta') }}
                                                            </p>
                                                            <div class="flex flex-wrap gap-2">
                                                                @if ($consultation['main_service'])
                                                                    <flux:badge color="sky" size="sm">{{ $consultation['main_service'] }}</flux:badge>
                                                                @endif
                                                                @if ($consultation['derived_service'])
                                                                    <flux:badge color="blue" size="sm">{{ __('Derivado: :service', ['service' => $consultation['derived_service']]) }}</flux:badge>
                                                                @endif
                                                                @if (! $consultation['main_service'] && ! $consultation['derived_service'])
                                                                    <span class="text-sm text-portal-muted dark:text-zinc-400">{{ __('Sin servicio registrado') }}</span>
                                                                @endif
                                                            </div>

                                                            @if (filled($consultation['reason_consultation']))
                                                                <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Motivo de consulta') }}</p>
                                                                    <p class="mt-1.5 text-sm leading-relaxed text-portal-ink dark:text-zinc-100">{{ $consultation['reason_consultation'] }}</p>
                                                                </div>
                                                            @endif

                                                            @if (filled($consultation['diagnostic_impression']))
                                                                <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                                    <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Impresión diagnóstica') }}</p>
                                                                    <p class="mt-1.5 text-sm leading-relaxed text-portal-ink dark:text-zinc-100">{{ $consultation['diagnostic_impression'] }}</p>
                                                                </div>
                                                            @endif

                                                            @if (! filled($consultation['reason_consultation']) && ! filled($consultation['diagnostic_impression']))
                                                                <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                                    <p class="text-sm text-portal-muted dark:text-zinc-400">{{ __('No hay un resumen narrativo registrado para esta consulta.') }}</p>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <div class="space-y-3">
                                                            <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                                <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Signos vitales') }}</p>
                                                                @php
                                                                    $visibleVitals = collect($consultation['vitals'])
                                                                        ->filter(fn ($value) => filled($value))
                                                                        ->all();
                                                                @endphp
                                                                @if (count($visibleVitals) > 0)
                                                                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                                                        @foreach ($visibleVitals as $label => $value)
                                                                            <div class="rounded-md bg-portal-subtle px-2 py-1.5 text-portal-ink dark:bg-zinc-800 dark:text-zinc-200">
                                                                                <span class="font-semibold">{{ $label }}:</span>
                                                                                {{ $value }}
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <p class="mt-2 text-sm text-portal-muted dark:text-zinc-400">{{ __('No se registraron signos vitales en esta consulta.') }}</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                                                        <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Medicamentos') }}</p>
                                                                <flux:badge color="sky" size="sm">{{ $consultation['medications']->count() }}</flux:badge>
                                                            </div>
                                                            @if ($consultation['medications']->isNotEmpty())
                                                                <ul class="mt-2 space-y-1.5 text-sm text-portal-ink dark:text-zinc-200">
                                                                    @foreach ($consultation['medications']->take(3) as $item)
                                                                        <li>{{ $item->medicine }} @if(filled($item->duration)) <span class="text-xs text-portal-muted">({{ __(':n días', ['n' => $item->duration]) }})</span>@endif</li>
                                                                    @endforeach
                                                                    @if ($consultation['medications']->count() > 3)
                                                                        <li class="text-xs text-portal-muted dark:text-zinc-400">{{ __('+ :n más', ['n' => $consultation['medications']->count() - 3]) }}</li>
                                                                    @endif
                                                                </ul>
                                                            @else
                                                                <p class="mt-2 text-sm text-portal-muted dark:text-zinc-400">{{ __('Sin medicamentos') }}</p>
                                                            @endif
                                                        </div>

                                                        <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Laboratorios') }}</p>
                                                                <flux:badge color="blue" size="sm">{{ $consultation['labs']->count() }}</flux:badge>
                                                            </div>
                                                            @if ($consultation['labs']->isNotEmpty())
                                                                <ul class="mt-2 space-y-1.5 text-sm text-portal-ink dark:text-zinc-200">
                                                                    @foreach ($consultation['labs']->take(3) as $item)
                                                                        <li>{{ $item->laboratory }} <span class="text-xs text-portal-muted">({{ $item->status }})</span></li>
                                                                    @endforeach
                                                                    @if ($consultation['labs']->count() > 3)
                                                                        <li class="text-xs text-portal-muted dark:text-zinc-400">{{ __('+ :n más', ['n' => $consultation['labs']->count() - 3]) }}</li>
                                                                    @endif
                                                                </ul>
                                                            @else
                                                                <p class="mt-2 text-sm text-portal-muted dark:text-zinc-400">{{ __('Sin labs') }}</p>
                                                            @endif
                                                        </div>

                                                        <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Estudios / imágenes') }}</p>
                                                                <flux:badge color="purple" size="sm">{{ $consultation['studies']->count() }}</flux:badge>
                                                            </div>
                                                            @if ($consultation['studies']->isNotEmpty())
                                                                <ul class="mt-2 space-y-1.5 text-sm text-portal-ink dark:text-zinc-200">
                                                                    @foreach ($consultation['studies']->take(3) as $item)
                                                                        <li>{{ $item->study }} <span class="text-xs text-portal-muted">({{ $item->status }})</span></li>
                                                                    @endforeach
                                                                    @if ($consultation['studies']->count() > 3)
                                                                        <li class="text-xs text-portal-muted dark:text-zinc-400">{{ __('+ :n más', ['n' => $consultation['studies']->count() - 3]) }}</li>
                                                                    @endif
                                                                </ul>
                                                            @else
                                                                <p class="mt-2 text-sm text-portal-muted dark:text-zinc-400">{{ __('Sin estudios') }}</p>
                                                            @endif
                                                        </div>

                                                        <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Especialidades derivadas') }}</p>
                                                                <flux:badge color="amber" size="sm">{{ $consultation['specialties']->count() }}</flux:badge>
                                                            </div>
                                                            @if ($consultation['specialties']->isNotEmpty())
                                                                <ul class="mt-2 space-y-1.5 text-sm text-portal-ink dark:text-zinc-200">
                                                                    @foreach ($consultation['specialties']->take(3) as $item)
                                                                        <li>{{ $item->specialty }}</li>
                                                                    @endforeach
                                                                    @if ($consultation['specialties']->count() > 3)
                                                                        <li class="text-xs text-portal-muted dark:text-zinc-400">{{ __('+ :n más', ['n' => $consultation['specialties']->count() - 3]) }}</li>
                                                                    @endif
                                                                </ul>
                                                            @else
                                                                <p class="mt-2 text-sm text-portal-muted dark:text-zinc-400">{{ __('Sin especialidades') }}</p>
                                                            @endif
                                                        </div>

                                                        <div class="portal-liquid-section rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Reportes médicos') }}</p>
                                                                <flux:badge color="sky" size="sm">{{ $consultation['reports']->count() }}</flux:badge>
                                                            </div>
                                                            @if ($consultation['reports']->isNotEmpty())
                                                                <ul class="mt-2 space-y-1.5 text-sm text-portal-ink dark:text-zinc-200">
                                                                    @foreach ($consultation['reports']->take(3) as $item)
                                                                        <li>{{ __('Reporte #:id', ['id' => $item->id]) }} <span class="text-xs text-portal-muted">({{ $item->status ?: __('sin estado') }})</span></li>
                                                                    @endforeach
                                                                    @if ($consultation['reports']->count() > 3)
                                                                        <li class="text-xs text-portal-muted dark:text-zinc-400">{{ __('+ :n más', ['n' => $consultation['reports']->count() - 3]) }}</li>
                                                                    @endif
                                                                </ul>
                                                            @else
                                                                <p class="mt-2 text-sm text-portal-muted dark:text-zinc-400">{{ __('Sin reportes') }}</p>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if (filled($consultation['observations']))
                                                        <div class="portal-liquid-section mt-4 rounded-lg px-3.5 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-portal-muted dark:text-zinc-400">{{ __('Qué debes recordar') }}</p>
                                                            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-portal-ink dark:text-zinc-100">{{ $consultation['observations'] }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            @empty
                                                <p class="text-sm text-portal-muted dark:text-zinc-400">{{ __('Este caso aún no tiene consultas registradas.') }}</p>
                                            @endforelse
                                        </div>
                                    </flux:accordion.content>
                                </flux:accordion.item>

                                @if ($case['reports_without_consultation']->isNotEmpty())
                                    <flux:accordion.item
                                        class="portal-glass-panel overflow-hidden rounded-xl border border-portal-border-soft bg-portal-surface !border-b-0 !pb-0 !pt-0 dark:border-zinc-700 dark:bg-zinc-900/70"
                                    >
                                        <flux:accordion.heading class="px-4 py-3.5 text-sm font-semibold text-portal-ink dark:text-zinc-100">
                                            <span class="flex items-center gap-2">
                                                <flux:icon name="document-text" class="size-4 text-portal-primary dark:text-portal-primary" />
                                                {{ __('Reportes médicos del caso (sin consulta asociada)') }}
                                            </span>
                                        </flux:accordion.heading>
                                        <flux:accordion.content class="border-t border-portal-border-soft px-4 py-4 dark:border-zinc-700">
                                            <ul class="space-y-2">
                                                @foreach ($case['reports_without_consultation'] as $report)
                                                    <li class="rounded-lg border border-portal-border-soft bg-portal-subtle/50 px-3 py-2 text-sm text-portal-ink dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-200">
                                                        {{ __('Reporte #:id', ['id' => $report->id]) }}
                                                        @if ($report->created_at)
                                                            <span class="text-portal-muted dark:text-zinc-400">— {{ $report->created_at->translatedFormat('d M Y H:i') }}</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </flux:accordion.content>
                                    </flux:accordion.item>
                                @endif
                            </flux:accordion>
                        </div>
                    </x-portal.card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
