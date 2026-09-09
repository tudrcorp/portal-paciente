<x-layouts.app :title="__('Completar historia clínica')">
    @php
        $steps = [
            ['key' => 'allergies', 'label' => __('Alergias')],
            ['key' => 'pathological', 'label' => __('Antecedentes')],
            ['key' => 'habits', 'label' => __('Hábitos')],
            ['key' => 'surgical', 'label' => __('Cirugías')],
            ['key' => 'family', 'label' => __('Familia')],
        ];

        if ($isFemale) {
            $steps[] = ['key' => 'gyn', 'label' => __('Ginecológico')];
        }

        $steps[] = ['key' => 'review', 'label' => __('Revisar')];
        $totalSteps = count($steps);
    @endphp

    <div
        class="portal-stack-appear portal-typography-fluid mx-auto flex w-full max-w-[1220px] flex-col gap-6 motion-safe:animate-portal-fade xl:gap-8"
        x-data="clinicalHistoryWizard(@js($totalSteps), @js($isFemale))"
    >
        <header class="flex flex-col gap-4 border-b border-portal-border-soft py-6 dark:border-zinc-700">
            <div class="flex items-start gap-4">
                <span
                    class="mt-0.5 flex size-12 shrink-0 items-center justify-center rounded-2xl bg-portal-primary-soft text-portal-primary ring-1 ring-portal-primary/25"
                    aria-hidden="true"
                >
                    <flux:icon name="clipboard-document-check" class="size-7" />
                </span>
                <div class="min-w-0">
                    <flux:heading size="xl" class="font-semibold tracking-tight text-portal-ink dark:text-zinc-100">
                        {{ __('Completa tu historia clínica') }}
                    </flux:heading>
                    <flux:subheading class="mt-1 max-w-3xl text-base leading-relaxed text-portal-muted dark:text-zinc-400">
                        {{ __('Es un paso obligatorio y solo toma unos minutos. Responde con calma: puedes indicar “ninguna” cuando no aplique.') }}
                    </flux:subheading>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <p class="font-medium text-portal-ink dark:text-zinc-100">
                        <span x-text="stepLabel()"></span>
                    </p>
                    <p class="text-portal-muted dark:text-zinc-400">
                        {{ __('Paso') }}
                        <span x-text="step"></span>
                        {{ __('de') }}
                        {{ $totalSteps }}
                    </p>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-portal-subtle dark:bg-zinc-800">
                    <div
                        class="h-full rounded-full bg-portal-primary transition-all duration-300"
                        :style="`width: ${progress()}%`"
                    ></div>
                </div>
            </div>
        </header>

        <x-portal.alert variant="info" :title="__('Tu información es confidencial')">
            {{ __('Solo el equipo clínico la usará para tu atención. Si no estás seguro de algo, déjalo en blanco o marca que no aplica.') }}
        </x-portal.alert>

        <form
            method="POST"
            action="{{ route('history.onboarding.store') }}"
            class="space-y-5"
            @submit="if (step < totalSteps) { $event.preventDefault(); next() }"
        >
            @csrf

            {{-- Paso 1: Alergias --}}
            <x-glass-panel variant="pure" class="!p-5 sm:!p-6" x-show="step === 1" x-cloak>
                <h2 class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ __('Alergias') }}</h2>
                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                    {{ __('Medicamentos, alimentos u otras alergias que debamos conocer.') }}
                </p>

                <label class="mt-5 flex cursor-pointer items-center gap-3 rounded-xl border border-portal-border-soft bg-portal-subtle/40 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <input
                        type="checkbox"
                        name="no_allergies"
                        value="1"
                        class="size-5 rounded border-portal-border text-portal-primary focus:ring-portal-primary"
                        x-model="noAllergies"
                    />
                    <span class="text-sm font-medium text-portal-ink dark:text-zinc-100">
                        {{ __('No tengo alergias conocidas') }}
                    </span>
                </label>

                <div class="mt-4 space-y-4" x-show="! noAllergies">
                    <flux:field>
                        <flux:label>{{ __('¿A qué eres alérgico/a?') }}</flux:label>
                        <flux:textarea
                            name="allergies"
                            rows="3"
                            x-model="allergies"
                            placeholder="{{ __('Ej.: penicilina, mariscos…') }}"
                        />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Observaciones (opcional)') }}</flux:label>
                        <flux:textarea
                            name="observations_allergies"
                            rows="2"
                            x-model="allergiesObs"
                            placeholder="{{ __('Reacciones, severidad, etc.') }}"
                        />
                    </flux:field>
                </div>
            </x-glass-panel>

            {{-- Paso 2: Patológicos --}}
            <x-glass-panel variant="pure" class="!p-5 sm:!p-6" x-show="step === 2" x-cloak>
                <h2 class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes personales') }}</h2>
                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                    {{ __('Marca solo las condiciones que tienes o has tenido.') }}
                </p>

                <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pathologicalFlags as $key => $label)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-portal-border-soft/80 bg-portal-subtle/30 px-3 py-3 transition hover:bg-portal-subtle/60 dark:border-zinc-700 dark:bg-zinc-900/30">
                            <input
                                type="checkbox"
                                name="{{ $key }}"
                                value="1"
                                class="mt-0.5 size-5 rounded border-portal-border text-portal-primary focus:ring-portal-primary"
                            />
                            <span class="text-sm font-medium leading-snug text-portal-ink dark:text-zinc-100">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-5 space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Medicamentos o suplementos actuales') }}</flux:label>
                        <flux:textarea name="medications_supplements" rows="2" placeholder="{{ __('Ej.: metformina, vitaminas…') }}" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Observaciones (opcional)') }}</flux:label>
                        <flux:textarea name="observations_pathological" rows="2" />
                    </flux:field>
                </div>
            </x-glass-panel>

            {{-- Paso 3: Hábitos --}}
            <x-glass-panel variant="pure" class="!p-5 sm:!p-6" x-show="step === 3" x-cloak>
                <h2 class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ __('Hábitos') }}</h2>
                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                    {{ __('Responde con sinceridad; nos ayuda a cuidarte mejor.') }}
                </p>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($habitFlags as $key => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-portal-border-soft bg-portal-subtle/40 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <input
                                type="checkbox"
                                name="{{ $key }}"
                                value="1"
                                class="size-5 rounded border-portal-border text-portal-primary focus:ring-portal-primary"
                            />
                            <span class="text-sm font-semibold text-portal-ink dark:text-zinc-100">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-5">
                    <flux:field>
                        <flux:label>{{ __('Observaciones (opcional)') }}</flux:label>
                        <flux:textarea name="observations_not_pathological" rows="2" />
                    </flux:field>
                </div>
            </x-glass-panel>

            {{-- Paso 4: Quirúrgicos --}}
            <x-glass-panel variant="pure" class="!p-5 sm:!p-6" x-show="step === 4" x-cloak>
                <h2 class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes quirúrgicos') }}</h2>
                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                    {{ __('Cirugías previas, fechas aproximadas o “ninguna”.') }}
                </p>

                <div class="mt-5">
                    <flux:field>
                        <flux:label>{{ __('Cirugías o procedimientos') }}</flux:label>
                        <flux:textarea
                            name="history_surgical"
                            rows="4"
                            placeholder="{{ __('Ej.: apendicectomía (2018). Si no has tenido, escribe Ninguna.') }}"
                        />
                    </flux:field>
                </div>
            </x-glass-panel>

            {{-- Paso 5: Familiares --}}
            <x-glass-panel variant="pure" class="!p-5 sm:!p-6" x-show="step === 5" x-cloak>
                <h2 class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes familiares') }}</h2>
                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                    {{ __('Condiciones de padres, hermanos u otros familiares cercanos.') }}
                </p>

                <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($familyFlags as $key => $label)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-portal-border-soft/80 bg-portal-subtle/30 px-3 py-3 dark:border-zinc-700 dark:bg-zinc-900/30">
                            <input
                                type="checkbox"
                                name="{{ $key }}"
                                value="1"
                                class="mt-0.5 size-5 rounded border-portal-border text-portal-primary focus:ring-portal-primary"
                            />
                            <span class="text-sm font-medium leading-snug text-portal-ink dark:text-zinc-100">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-5">
                    <flux:field>
                        <flux:label>{{ __('Observaciones familiares (opcional)') }}</flux:label>
                        <flux:textarea name="observations_personal" rows="2" />
                    </flux:field>
                </div>
            </x-glass-panel>

            {{-- Paso 6: Ginecológico (solo mujeres) --}}
            @if ($isFemale)
                <x-glass-panel variant="pure" class="!p-5 sm:!p-6" x-show="step === 6" x-cloak>
                    <h2 class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ __('Antecedentes ginecológicos') }}</h2>
                    <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                        {{ __('Completa solo lo que recuerdes. Los campos son opcionales.') }}
                    </p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Edad de la primera menstruación') }}</flux:label>
                            <flux:input name="edad_primera_menstruation" type="text" placeholder="{{ __('Ej.: 12') }}" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Fecha de última regla') }}</flux:label>
                            <flux:input name="fecha_ultima_regla" type="text" placeholder="{{ __('Ej.: 15/03/2026') }}" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Número de embarazos') }}</flux:label>
                            <flux:input name="numero_embarazos" type="number" min="0" max="30" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Número de partos') }}</flux:label>
                            <flux:input name="numero_partos" type="number" min="0" max="30" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Número de abortos') }}</flux:label>
                            <flux:input name="numero_abortos" type="number" min="0" max="30" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Cesáreas') }}</flux:label>
                            <flux:input name="cesareas" type="number" min="0" max="30" />
                        </flux:field>
                    </div>

                    <div class="mt-4">
                        <flux:field>
                            <flux:label>{{ __('Observaciones (opcional)') }}</flux:label>
                            <flux:textarea name="observations_ginecologica" rows="2" />
                        </flux:field>
                    </div>
                </x-glass-panel>
            @endif

            {{-- Revisión --}}
            <x-glass-panel variant="pure" class="!p-5 sm:!p-6" x-show="step === totalSteps" x-cloak>
                <h2 class="text-lg font-semibold text-portal-ink dark:text-zinc-100">{{ __('Revisa y confirma') }}</h2>
                <p class="mt-1 text-sm text-portal-muted dark:text-zinc-400">
                    {{ __('Al guardar, tu historia quedará registrada y podrás usar el portal con normalidad.') }}
                </p>

                <ul class="mt-5 space-y-3 text-sm text-portal-ink dark:text-zinc-200">
                    <li class="rounded-xl border border-portal-border-soft/70 bg-portal-subtle/35 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/35">
                        <span class="font-semibold">{{ __('Alergias:') }}</span>
                        <span x-text="noAllergies ? @js(__('Ninguna conocida')) : (allergies || @js(__('Sin detalle')))"></span>
                    </li>
                    <li class="rounded-xl border border-portal-border-soft/70 bg-portal-subtle/35 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/35">
                        {{ __('Incluiste antecedentes personales, hábitos, cirugías y familiares en los pasos anteriores.') }}
                    </li>
                    @if ($isFemale)
                        <li class="rounded-xl border border-portal-border-soft/70 bg-portal-subtle/35 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/35">
                            {{ __('También registraste (o dejaste en blanco) los datos ginecológicos.') }}
                        </li>
                    @endif
                </ul>

                @if ($errors->any())
                    <div class="mt-4">
                        <x-portal.alert variant="danger" :title="__('Revisa los datos')">
                            <ul class="list-disc ps-4">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-portal.alert>
                    </div>
                @endif
            </x-glass-panel>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="arrow-left"
                    x-show="step > 1"
                    @click="prev()"
                >
                    {{ __('Anterior') }}
                </flux:button>
                <span x-show="step === 1" class="hidden sm:block"></span>

                <div class="flex gap-2">
                    <flux:button
                        type="button"
                        variant="primary"
                        icon-trailing="arrow-right"
                        x-show="step < totalSteps"
                        @click="next()"
                        data-test="history-wizard-next"
                    >
                        {{ __('Siguiente') }}
                    </flux:button>

                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="check"
                        x-show="step === totalSteps"
                        data-test="history-wizard-submit"
                    >
                        {{ __('Guardar y entrar al portal') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function clinicalHistoryWizard(totalSteps, isFemale) {
            const labels = [
                @js(__('Alergias')),
                @js(__('Antecedentes personales')),
                @js(__('Hábitos')),
                @js(__('Cirugías')),
                @js(__('Familia')),
                ...(isFemale ? [@js(__('Ginecológico'))] : []),
                @js(__('Revisar y confirmar')),
            ];

            return {
                step: 1,
                totalSteps,
                noAllergies: false,
                allergies: '',
                allergiesObs: '',
                progress() {
                    return Math.round((this.step / this.totalSteps) * 100);
                },
                stepLabel() {
                    return labels[this.step - 1] || '';
                },
                next() {
                    if (this.step < this.totalSteps) {
                        this.step += 1;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },
                prev() {
                    if (this.step > 1) {
                        this.step -= 1;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },
            };
        }
    </script>
</x-layouts.app>
