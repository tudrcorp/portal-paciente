@php
    /** @var array<string, mixed>|null $reminder */
    $isEdit = is_array($reminder);
    $r = $reminder ?? [];
    $selectedDays = $r['schedule_days'] ?? [1, 2, 3, 4, 5];
    $selectedLeads = $r['lead_minutes'] ?? [1440, 60];
@endphp

<x-portal.card class="mb-4">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        <input type="hidden" name="type" value="{{ $formType }}">

        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg" class="text-portal-ink dark:text-zinc-100">
                {{ $isEdit ? __('Editar recordatorio') : __('Nuevo recordatorio') }}
            </flux:heading>
            <flux:button type="button" variant="ghost" @click="createOpen = false; editingId = null">
                {{ __('Cerrar') }}
            </flux:button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @if (in_array($formType, ['chronic_treatment', 'specific_treatment'], true))
                <div class="grid gap-4 md:col-span-2 md:grid-cols-3">
                    <flux:input name="title" :label="__('Título')" :value="old('title', $r['title'] ?? '')" required />
                    <flux:input name="medicine_name" :label="__('Medicamento / tratamiento')" :value="old('medicine_name', $r['medicine_name'] ?? '')" />
                    <flux:input name="dosage" :label="__('Dosis')" :value="old('dosage', $r['dosage'] ?? '')" />
                </div>
                <div class="md:col-span-2">
                    @php
                        $timesValue = old('schedule_times', $r['schedule_times_input'] ?? '08:00, 20:00');
                        if (is_array($timesValue)) {
                            $timesValue = implode(', ', $timesValue);
                        }
                    @endphp
                    <flux:input
                        name="schedule_times"
                        :label="__('¿A qué horas te toca tomarlo?')"
                        :value="$timesValue"
                        placeholder="08:00, 20:00"
                        required
                    />
                    <p class="mt-2 text-sm leading-relaxed text-portal-muted dark:text-zinc-400">
                        {{ __('Escribe las horas en formato 24 horas, separadas por coma. Por ejemplo: si lo tomas en la mañana y en la noche, pon 08:00, 20:00. Si es solo una vez al día, con una hora basta (como 09:00). Esas mismas horas se repetirán en cada día que marques abajo.') }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <p class="mb-2 text-sm font-medium text-portal-ink dark:text-zinc-200">{{ __('Días de la semana') }}</p>
                    <p class="mb-2 text-xs text-portal-muted dark:text-zinc-400">
                        {{ __('Marca los días activos (por ejemplo Lun/Mié/Vie para interdiario). Las horas que indiques sonararán ese mismo día.') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($dayLabels as $dayValue => $dayLabel)
                            <label class="inline-flex items-center gap-2 rounded-xl border border-portal-border-soft px-3 py-2 text-sm dark:border-zinc-700">
                                <input
                                    type="checkbox"
                                    name="schedule_days[]"
                                    value="{{ $dayValue }}"
                                    @checked(in_array((int) $dayValue, array_map('intval', (array) old('schedule_days', $selectedDays)), true))
                                >
                                {{ $dayLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <flux:input type="date" name="starts_on" :label="__('Desde')" :value="old('starts_on', $r['starts_on'] ?? now()->toDateString())" />
                <flux:input
                    type="date"
                    name="ends_on"
                    :label="$formType === 'specific_treatment' ? __('Hasta (obligatorio)') : __('Hasta (opcional)')"
                    :value="old('ends_on', $r['ends_on'] ?? '')"
                    :required="$formType === 'specific_treatment'"
                />
            @endif

            @if ($formType === 'appointment')
                <div class="md:col-span-2">
                    <flux:input name="title" :label="__('Título')" :value="old('title', $r['title'] ?? '')" required />
                </div>
                <flux:input type="datetime-local" name="appointment_at" :label="__('Fecha y hora de la cita')" :value="old('appointment_at', $r['appointment_at'] ?? '')" required />
                <flux:input name="doctor_name" :label="__('Médico')" :value="old('doctor_name', $r['doctor_name'] ?? '')" />
                <flux:input name="location" :label="__('Lugar')" :value="old('location', $r['location'] ?? '')" class="md:col-span-2" />
                <div class="md:col-span-2">
                    <p class="mb-2 text-sm font-medium text-portal-ink dark:text-zinc-200">{{ __('Avisar con anticipación') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([1440 => __('1 día antes'), 60 => __('1 hora antes'), 30 => __('30 minutos antes')] as $minutes => $label)
                            <label class="inline-flex items-center gap-2 rounded-xl border border-portal-border-soft px-3 py-2 text-sm dark:border-zinc-700">
                                <input
                                    type="checkbox"
                                    name="lead_minutes[]"
                                    value="{{ $minutes }}"
                                    @checked(in_array((int) $minutes, array_map('intval', (array) old('lead_minutes', $selectedLeads)), true))
                                >
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="md:col-span-2">
                <flux:textarea name="notes" :label="__('Notas')" rows="3">{{ old('notes', $r['notes'] ?? '') }}</flux:textarea>
            </div>
        </div>

        <div>
            <p class="mb-2 text-sm font-medium text-portal-ink dark:text-zinc-200">{{ __('Canales') }}</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="inline-flex items-center gap-2 rounded-xl border border-portal-border-soft px-3 py-2 text-sm dark:border-zinc-700">
                    <input type="checkbox" name="channel_whatsapp" value="1" @checked(old('channel_whatsapp', $r['channel_whatsapp'] ?? $hasPhone)) @disabled(! $hasPhone)>
                    {{ __('WhatsApp') }}
                    @unless ($hasPhone)
                        <span class="text-xs text-portal-muted">({{ __('sin teléfono') }})</span>
                    @endunless
                </label>
                <label class="inline-flex items-center gap-2 rounded-xl border border-portal-border-soft px-3 py-2 text-sm dark:border-zinc-700">
                    <input type="checkbox" name="channel_in_app" value="1" @checked(old('channel_in_app', $r['channel_in_app'] ?? true))>
                    {{ __('In-app') }}
                </label>
                <label class="inline-flex items-center gap-2 rounded-xl border border-portal-border-soft px-3 py-2 text-sm dark:border-zinc-700">
                    <input type="checkbox" name="channel_sms" value="1" @checked(old('channel_sms', $r['channel_sms'] ?? false))>
                    {{ __('SMS') }} <span class="text-xs text-portal-muted">({{ __('próximamente') }})</span>
                </label>
                <label class="inline-flex items-center gap-2 rounded-xl border border-portal-border-soft px-3 py-2 text-sm dark:border-zinc-700">
                    <input type="checkbox" name="channel_push" value="1" @checked(old('channel_push', $r['channel_push'] ?? false))>
                    {{ __('Push') }} <span class="text-xs text-portal-muted">({{ __('próximamente') }})</span>
                </label>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <flux:button type="button" variant="ghost" @click="createOpen = false; editingId = null">{{ __('Cancelar') }}</flux:button>
            <flux:button type="submit" variant="primary" class="portal-glass-button">
                {{ $isEdit ? __('Guardar cambios') : __('Crear recordatorio') }}
            </flux:button>
        </div>
    </form>
</x-portal.card>
