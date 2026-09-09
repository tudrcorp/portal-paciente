<?php

declare(strict_types=1);

namespace App\Services\PortalData;

use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;
use App\Services\PortalApi\PortalApiClient;
use App\Support\PatientNotificationsPresentation;
use App\Support\PortalDataSource;

/**
 * Recordatorios: lectura/escritura vía DB o API.
 * Normaliza la respuesta del API a la misma forma que espera Blade.
 */
final class RemindersGateway
{
    public function __construct(private readonly PortalApiClient $api) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(TelemedicinePatient $patient): array
    {
        if (PortalDataSource::usesApi()) {
            return $this->normalizeApiIndex($this->api->reminders());
        }

        return PatientNotificationsPresentation::forPatient($patient);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(TelemedicinePatient $patient, array $payload): void
    {
        if (PortalDataSource::usesApi()) {
            $this->api->createReminder($payload);

            return;
        }

        PatientReminder::query()->create([
            ...$payload,
            'telemedicine_patient_id' => $patient->getKey(),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(TelemedicinePatient $patient, int $reminderId, array $payload): string
    {
        if (PortalDataSource::usesApi()) {
            $updated = $this->api->updateReminder($reminderId, $payload);

            return $this->tabForType((string) ($updated['type'] ?? $payload['type'] ?? PatientReminder::TYPE_CHRONIC));
        }

        $reminder = $this->ownedReminder($patient, $reminderId);
        $reminder->update($payload);

        return $this->tabForType((string) $reminder->type);
    }

    public function destroy(TelemedicinePatient $patient, int $reminderId): string
    {
        if (PortalDataSource::usesApi()) {
            $type = $this->findTypeFromApi($reminderId);
            $this->api->deleteReminder($reminderId);

            return $this->tabForType($type);
        }

        $reminder = $this->ownedReminder($patient, $reminderId);
        $tab = $this->tabForType((string) $reminder->type);
        $reminder->delete();

        return $tab;
    }

    /**
     * @return array{tab: string, message: string}
     */
    public function toggle(TelemedicinePatient $patient, int $reminderId): array
    {
        if (PortalDataSource::usesApi()) {
            $updated = $this->api->toggleReminder($reminderId);
            $active = (bool) ($updated['is_active'] ?? false);

            return [
                'tab' => $this->tabForType((string) ($updated['type'] ?? PatientReminder::TYPE_CHRONIC)),
                'message' => $active
                    ? __('Recordatorio activado. El ciclo reinicia desde hoy.')
                    : __('Recordatorio pausado.'),
            ];
        }

        $reminder = $this->ownedReminder($patient, $reminderId);
        $activating = ! $reminder->is_active;

        $payload = ['is_active' => $activating];
        if ($activating && in_array($reminder->type, [
            PatientReminder::TYPE_CHRONIC,
            PatientReminder::TYPE_SPECIFIC,
        ], true)) {
            $payload['starts_on'] = now()->toDateString();
        }

        $reminder->update($payload);

        return [
            'tab' => $this->tabForType((string) $reminder->type),
            'message' => $reminder->is_active
                ? __('Recordatorio activado. El ciclo reinicia desde hoy.')
                : __('Recordatorio pausado.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeApiIndex(array $data): array
    {
        $dayLabels = PatientNotificationsPresentation::dayLabels();

        return [
            'has_phone' => (bool) ($data['has_phone'] ?? false),
            'phone_display' => (string) ($data['phone_display'] ?? ''),
            'chronic' => array_map(fn ($r) => $this->normalizeReminderCard($r, $dayLabels), $data['chronic'] ?? []),
            'specific' => array_map(fn ($r) => $this->normalizeReminderCard($r, $dayLabels), $data['specific'] ?? []),
            'appointments' => array_map(fn ($r) => $this->normalizeReminderCard($r, $dayLabels), $data['appointments'] ?? []),
            'history' => array_map(fn ($h) => $this->normalizeHistoryCard($h), $data['history'] ?? []),
            'counts' => $data['counts'] ?? [
                'chronic' => 0,
                'specific' => 0,
                'appointments' => 0,
                'active' => 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $reminder
     * @param  array<int, string>  $dayLabels
     * @return array<string, mixed>
     */
    private function normalizeReminderCard(array $reminder, array $dayLabels): array
    {
        $times = array_values(array_filter($reminder['schedule_times'] ?? []));
        $days = array_map('intval', $reminder['schedule_days'] ?? []);
        $dayNames = array_values(array_filter(array_map(
            fn ($d) => $dayLabels[$d] ?? null,
            $days
        )));

        $channels = $reminder['channels'] ?? [];
        if ($channels === [] && ! empty($reminder['channels_labels'])) {
            $channels = $reminder['channels_labels'];
        }

        $summary = $reminder['summary'] ?? null;
        if (! is_string($summary) || $summary === '') {
            if (($reminder['type'] ?? '') === PatientReminder::TYPE_APPOINTMENT) {
                $summary = trim((string) ($reminder['appointment_at'] ?? ''));
            } else {
                $summary = collect([
                    $reminder['medicine_name'] ?? null,
                    $times !== [] ? implode(', ', $times) : null,
                    $dayNames !== [] ? implode(', ', $dayNames) : null,
                ])->filter()->implode(' · ');
            }
        }

        return [
            'id' => (int) ($reminder['id'] ?? 0),
            'type' => $reminder['type'] ?? PatientReminder::TYPE_CHRONIC,
            'title' => $reminder['title'] ?? '',
            'notes' => $reminder['notes'] ?? null,
            'medicine_name' => $reminder['medicine_name'] ?? null,
            'dosage' => $reminder['dosage'] ?? null,
            'appointment_at' => $reminder['appointment_at'] ?? null,
            'appointment_display' => $reminder['appointment_display'] ?? null,
            'location' => $reminder['location'] ?? null,
            'doctor_name' => $reminder['doctor_name'] ?? null,
            'schedule_times' => $times,
            'schedule_times_input' => implode(', ', $times),
            'schedule_days' => $days,
            'schedule_days_labels' => $dayNames,
            'starts_on' => $reminder['starts_on'] ?? null,
            'ends_on' => $reminder['ends_on'] ?? null,
            'lead_minutes' => array_map('intval', $reminder['lead_minutes'] ?? []),
            'lead_minutes_labels' => $reminder['lead_minutes_labels'] ?? [],
            'channel_whatsapp' => (bool) ($reminder['channel_whatsapp'] ?? false),
            'channel_in_app' => (bool) ($reminder['channel_in_app'] ?? false),
            'channel_sms' => (bool) ($reminder['channel_sms'] ?? false),
            'channel_push' => (bool) ($reminder['channel_push'] ?? false),
            'channels_labels' => $channels,
            'is_active' => (bool) ($reminder['is_active'] ?? false),
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeHistoryCard(array $item): array
    {
        $statusLabels = [
            'sent' => 'Enviado',
            'failed' => 'Fallido',
            'skipped' => 'Omitido',
            'pending' => 'Pendiente',
        ];
        $channelLabels = [
            'whatsapp' => 'WhatsApp',
            'in_app' => 'In-app',
            'sms' => 'SMS',
            'push' => 'Push',
        ];

        $channel = (string) ($item['channel'] ?? '');
        $status = (string) ($item['status'] ?? '');
        $message = null;
        if (is_array($item['provider_response'] ?? null)) {
            $message = $item['provider_response']['message'] ?? null;
        }

        return [
            'id' => (int) ($item['id'] ?? 0),
            'title' => $item['reminder_title'] ?? $item['title'] ?? 'Recordatorio',
            'channel' => $channelLabels[$channel] ?? $channel,
            'status' => $statusLabels[$status] ?? $status,
            'status_key' => $status,
            'scheduled_for' => $item['scheduled_for'] ?? '',
            'message' => $message,
            'error' => $item['error_message'] ?? $item['error'] ?? null,
        ];
    }

    private function findTypeFromApi(int $reminderId): string
    {
        $data = $this->api->reminders();
        foreach (['chronic', 'specific', 'appointments'] as $bucket) {
            foreach ($data[$bucket] ?? [] as $reminder) {
                if ((int) ($reminder['id'] ?? 0) === $reminderId) {
                    return (string) ($reminder['type'] ?? PatientReminder::TYPE_CHRONIC);
                }
            }
        }

        return PatientReminder::TYPE_CHRONIC;
    }

    private function ownedReminder(TelemedicinePatient $patient, int $reminderId): PatientReminder
    {
        $reminder = PatientReminder::query()
            ->where('id', $reminderId)
            ->where('telemedicine_patient_id', $patient->getKey())
            ->first();

        if (! $reminder) {
            abort(404);
        }

        return $reminder;
    }

    private function tabForType(string $type): string
    {
        return match ($type) {
            PatientReminder::TYPE_SPECIFIC => 'specific',
            PatientReminder::TYPE_APPOINTMENT => 'appointments',
            default => 'chronic',
        };
    }
}
