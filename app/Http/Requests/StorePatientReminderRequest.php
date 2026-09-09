<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;
use App\Support\CorporateWhatsApp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePatientReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof TelemedicinePatient;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'channel_whatsapp' => $this->boolean('channel_whatsapp'),
            'channel_in_app' => $this->boolean('channel_in_app'),
            'channel_sms' => $this->boolean('channel_sms'),
            'channel_push' => $this->boolean('channel_push'),
            'schedule_times' => $this->normalizeTimes($this->input('schedule_times')),
            'schedule_days' => $this->normalizeDays($this->input('schedule_days')),
            'lead_minutes' => $this->normalizeLeads($this->input('lead_minutes')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'type' => ['required', Rule::in(PatientReminder::TYPES)],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'medicine_name' => ['nullable', 'string', 'max:120'],
            'dosage' => ['nullable', 'string', 'max:80'],
            'appointment_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:160'],
            'doctor_name' => ['nullable', 'string', 'max:120'],
            'schedule_times' => ['nullable', 'array'],
            'schedule_times.*' => ['string', 'regex:/^([01]?\d|2[0-3]):([0-5]\d)$/'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_days.*' => ['integer', 'between:0,6'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'lead_minutes' => ['nullable', 'array'],
            'lead_minutes.*' => ['integer', 'min:0', 'max:10080'],
            'channel_whatsapp' => ['boolean'],
            'channel_in_app' => ['boolean'],
            'channel_sms' => ['boolean'],
            'channel_push' => ['boolean'],
        ];

        if ($type === PatientReminder::TYPE_APPOINTMENT) {
            $rules['appointment_at'] = ['required', 'date', 'after:now'];
            $rules['lead_minutes'] = ['required', 'array', 'min:1'];
        }

        if (in_array($type, [PatientReminder::TYPE_CHRONIC, PatientReminder::TYPE_SPECIFIC], true)) {
            $rules['schedule_times'] = ['required', 'array', 'min:1'];
            $rules['schedule_days'] = ['required', 'array', 'min:1'];
        }

        if ($type === PatientReminder::TYPE_SPECIFIC) {
            $rules['ends_on'] = ['required', 'date', 'after_or_equal:starts_on'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $channels = [
                $this->boolean('channel_whatsapp'),
                $this->boolean('channel_in_app'),
                $this->boolean('channel_sms'),
                $this->boolean('channel_push'),
            ];

            if (! in_array(true, $channels, true)) {
                $validator->errors()->add('channel_whatsapp', __('Activa al menos un canal de notificación.'));
            }

            if ($this->boolean('channel_whatsapp')) {
                /** @var TelemedicinePatient|null $user */
                $user = $this->user();
                $phone = $user instanceof TelemedicinePatient
                    ? CorporateWhatsApp::normalizePhoneForWhatsApp((string) ($user->phone ?: $user->phone_contact))
                    : null;

                if ($phone === null) {
                    $validator->errors()->add(
                        'channel_whatsapp',
                        __('Para usar WhatsApp necesitas un teléfono válido en tu perfil.')
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('Indica un título para el recordatorio.'),
            'appointment_at.required' => __('Indica la fecha y hora de la cita.'),
            'appointment_at.after' => __('La cita debe ser en el futuro.'),
            'schedule_times.required' => __('Agrega al menos una hora de alarma.'),
            'schedule_days.required' => __('Selecciona al menos un día de la semana.'),
            'ends_on.required' => __('Indica hasta cuándo aplica este tratamiento.'),
            'lead_minutes.required' => __('Elige con cuánta anticipación quieres el aviso.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reminderPayload(): array
    {
        return [
            'type' => $this->string('type')->toString(),
            'title' => $this->string('title')->trim()->toString(),
            'notes' => $this->filled('notes') ? $this->string('notes')->trim()->toString() : null,
            'medicine_name' => $this->filled('medicine_name') ? $this->string('medicine_name')->trim()->toString() : null,
            'dosage' => $this->filled('dosage') ? $this->string('dosage')->trim()->toString() : null,
            'appointment_at' => $this->input('type') === PatientReminder::TYPE_APPOINTMENT
                ? $this->input('appointment_at')
                : null,
            'location' => $this->filled('location') ? $this->string('location')->trim()->toString() : null,
            'doctor_name' => $this->filled('doctor_name') ? $this->string('doctor_name')->trim()->toString() : null,
            'schedule_times' => in_array($this->input('type'), [PatientReminder::TYPE_CHRONIC, PatientReminder::TYPE_SPECIFIC], true)
                ? $this->input('schedule_times', [])
                : null,
            'schedule_days' => in_array($this->input('type'), [PatientReminder::TYPE_CHRONIC, PatientReminder::TYPE_SPECIFIC], true)
                ? $this->input('schedule_days', [])
                : null,
            'starts_on' => $this->input('starts_on'),
            'ends_on' => $this->input('ends_on'),
            'lead_minutes' => $this->input('type') === PatientReminder::TYPE_APPOINTMENT
                ? $this->input('lead_minutes', [])
                : null,
            'channel_whatsapp' => $this->boolean('channel_whatsapp'),
            'channel_in_app' => $this->boolean('channel_in_app'),
            'channel_sms' => $this->boolean('channel_sms'),
            'channel_push' => $this->boolean('channel_push'),
        ];
    }

    private function normalizeTimes(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,;]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $times = [];
        foreach ($value as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $item, $m)) {
                $times[] = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            }
        }

        return array_values(array_unique($times));
    }

    private function normalizeDays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $days = [];
        foreach ($value as $day) {
            if (is_numeric($day)) {
                $days[] = (int) $day;
            }
        }

        return array_values(array_unique($days));
    }

    private function normalizeLeads(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $leads = [];
        foreach ($value as $item) {
            if (is_numeric($item)) {
                $leads[] = (int) $item;
            }
        }

        return array_values(array_unique($leads));
    }
}
