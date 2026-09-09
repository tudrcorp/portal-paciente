<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PatientNotificationDelivery;
use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;

final class PatientNotificationsPresentation
{
    /**
     * @return array{
     *     has_phone: bool,
     *     phone_display: string,
     *     chronic: list<array<string, mixed>>,
     *     specific: list<array<string, mixed>>,
     *     appointments: list<array<string, mixed>>,
     *     history: list<array<string, mixed>>,
     *     counts: array{chronic: int, specific: int, appointments: int, active: int}
     * }
     */
    public static function forPatient(TelemedicinePatient $patient): array
    {
        $reminders = PatientReminder::query()
            ->forPatient($patient->getKey())
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->get();

        $chronic = $reminders->where('type', PatientReminder::TYPE_CHRONIC)->values();
        $specific = $reminders->where('type', PatientReminder::TYPE_SPECIFIC)->values();
        $appointments = $reminders->where('type', PatientReminder::TYPE_APPOINTMENT)->values();

        $history = PatientNotificationDelivery::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->whereIn('channel', [
                PatientNotificationDelivery::CHANNEL_IN_APP,
                PatientNotificationDelivery::CHANNEL_WHATSAPP,
            ])
            ->whereIn('status', [
                PatientNotificationDelivery::STATUS_SENT,
                PatientNotificationDelivery::STATUS_FAILED,
                PatientNotificationDelivery::STATUS_SKIPPED,
            ])
            ->with('reminder')
            ->orderByDesc('scheduled_for')
            ->limit(20)
            ->get()
            ->map(fn (PatientNotificationDelivery $delivery): array => self::deliveryCard($delivery))
            ->all();

        $phone = CorporateWhatsApp::normalizePhoneForWhatsApp(
            (string) ($patient->phone ?: $patient->phone_contact)
        );

        return [
            'has_phone' => $phone !== null,
            'phone_display' => CorporateWhatsApp::formatDisplayPhone(
                (string) ($patient->phone ?: $patient->phone_contact)
            ),
            'chronic' => $chronic->map(fn (PatientReminder $r): array => self::reminderCard($r))->all(),
            'specific' => $specific->map(fn (PatientReminder $r): array => self::reminderCard($r))->all(),
            'appointments' => $appointments->map(fn (PatientReminder $r): array => self::reminderCard($r))->all(),
            'history' => $history,
            'counts' => [
                'chronic' => $chronic->count(),
                'specific' => $specific->count(),
                'appointments' => $appointments->count(),
                'active' => $reminders->where('is_active', true)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reminderCard(PatientReminder $reminder): array
    {
        $dayLabels = self::dayLabels();

        $days = collect($reminder->schedule_days ?? [])
            ->map(fn ($day) => $dayLabels[(int) $day] ?? null)
            ->filter()
            ->values()
            ->all();

        $times = collect($reminder->schedule_times ?? [])->filter()->values()->all();

        $channels = [];
        if ($reminder->channel_whatsapp) {
            $channels[] = 'WhatsApp';
        }
        if ($reminder->channel_in_app) {
            $channels[] = 'In-app';
        }
        if ($reminder->channel_sms) {
            $channels[] = 'SMS';
        }
        if ($reminder->channel_push) {
            $channels[] = 'Push';
        }

        return [
            'id' => $reminder->id,
            'type' => $reminder->type,
            'title' => $reminder->title,
            'notes' => $reminder->notes,
            'medicine_name' => $reminder->medicine_name,
            'dosage' => $reminder->dosage,
            'appointment_at' => $reminder->appointment_at?->format('Y-m-d\TH:i'),
            'appointment_display' => $reminder->appointment_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'location' => $reminder->location,
            'doctor_name' => $reminder->doctor_name,
            'schedule_times' => $times,
            'schedule_times_input' => implode(', ', $times),
            'schedule_days' => array_map('intval', $reminder->schedule_days ?? []),
            'schedule_days_labels' => $days,
            'starts_on' => $reminder->starts_on?->format('Y-m-d'),
            'ends_on' => $reminder->ends_on?->format('Y-m-d'),
            'lead_minutes' => array_map('intval', $reminder->lead_minutes ?? []),
            'lead_minutes_labels' => self::leadLabels($reminder->lead_minutes ?? []),
            'channel_whatsapp' => (bool) $reminder->channel_whatsapp,
            'channel_in_app' => (bool) $reminder->channel_in_app,
            'channel_sms' => (bool) $reminder->channel_sms,
            'channel_push' => (bool) $reminder->channel_push,
            'channels_labels' => $channels,
            'is_active' => (bool) $reminder->is_active,
            'summary' => self::summaryLine($reminder, $days, $times),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function deliveryCard(PatientNotificationDelivery $delivery): array
    {
        $statusLabels = [
            PatientNotificationDelivery::STATUS_SENT => 'Enviado',
            PatientNotificationDelivery::STATUS_FAILED => 'Fallido',
            PatientNotificationDelivery::STATUS_SKIPPED => 'Omitido',
            PatientNotificationDelivery::STATUS_PENDING => 'Pendiente',
        ];

        $channelLabels = [
            PatientNotificationDelivery::CHANNEL_WHATSAPP => 'WhatsApp',
            PatientNotificationDelivery::CHANNEL_IN_APP => 'In-app',
            PatientNotificationDelivery::CHANNEL_SMS => 'SMS',
            PatientNotificationDelivery::CHANNEL_PUSH => 'Push',
        ];

        $message = null;
        if (is_array($delivery->provider_response) && isset($delivery->provider_response['message'])) {
            $message = (string) $delivery->provider_response['message'];
        }

        return [
            'id' => $delivery->id,
            'title' => $delivery->reminder?->title ?? 'Recordatorio',
            'channel' => $channelLabels[$delivery->channel] ?? $delivery->channel,
            'status' => $statusLabels[$delivery->status] ?? $delivery->status,
            'status_key' => $delivery->status,
            'scheduled_for' => $delivery->scheduled_for?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'message' => $message,
            'error' => $delivery->error_message,
        ];
    }

    /**
     * @param  list<string>  $days
     * @param  list<string>  $times
     */
    private static function summaryLine(PatientReminder $reminder, array $days, array $times): string
    {
        if ($reminder->type === PatientReminder::TYPE_APPOINTMENT) {
            $parts = [];
            if ($reminder->appointment_at) {
                $parts[] = $reminder->appointment_at->timezone(config('app.timezone'))->format('d/m/Y H:i');
            }
            $leads = self::leadLabels($reminder->lead_minutes ?? []);
            if ($leads !== []) {
                $parts[] = 'aviso: '.implode(', ', $leads);
            }

            return implode(' · ', $parts);
        }

        $parts = [];
        if ($times !== []) {
            $parts[] = implode(', ', $times);
        }
        if ($days !== []) {
            $parts[] = implode(', ', $days);
        }
        if ($reminder->medicine_name) {
            array_unshift($parts, (string) $reminder->medicine_name);
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<int, string>
     */
    public static function dayLabels(): array
    {
        return [
            0 => 'Dom',
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
        ];
    }

    /**
     * @param  list<mixed>  $minutes
     * @return list<string>
     */
    private static function leadLabels(array $minutes): array
    {
        $labels = [];

        foreach ($minutes as $value) {
            $m = (int) $value;
            if ($m >= 1440 && $m % 1440 === 0) {
                $days = intdiv($m, 1440);
                $labels[] = $days === 1 ? '1 día antes' : "{$days} días antes";
            } elseif ($m >= 60 && $m % 60 === 0) {
                $hours = intdiv($m, 60);
                $labels[] = $hours === 1 ? '1 hora antes' : "{$hours} horas antes";
            } else {
                $labels[] = "{$m} min antes";
            }
        }

        return $labels;
    }
}
