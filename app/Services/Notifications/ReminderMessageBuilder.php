<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;

final class ReminderMessageBuilder
{
    public function build(PatientReminder $reminder, TelemedicinePatient $patient): string
    {
        $name = trim((string) ($patient->full_name ?? $patient->name ?? 'Paciente'));
        $greeting = $name !== '' ? "Hola {$name}," : 'Hola,';

        return match ($reminder->type) {
            PatientReminder::TYPE_APPOINTMENT => $this->appointmentMessage($greeting, $reminder),
            PatientReminder::TYPE_SPECIFIC => $this->treatmentMessage($greeting, $reminder, 'tratamiento'),
            default => $this->treatmentMessage($greeting, $reminder, 'tratamiento crónico'),
        };
    }

    private function appointmentMessage(string $greeting, PatientReminder $reminder): string
    {
        $when = $reminder->appointment_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'próximamente';
        $doctor = trim((string) $reminder->doctor_name);
        $location = trim((string) $reminder->location);

        $lines = [
            $greeting,
            '',
            'Te recordamos tu cita médica:',
            '• '.$reminder->title,
            '• Fecha y hora: '.$when,
        ];

        if ($doctor !== '') {
            $lines[] = '• Médico: '.$doctor;
        }

        if ($location !== '') {
            $lines[] = '• Lugar: '.$location;
        }

        if (filled($reminder->notes)) {
            $lines[] = '• Nota: '.trim((string) $reminder->notes);
        }

        $lines[] = '';
        $lines[] = 'Portal Paciente — Integracorp';

        return implode("\n", $lines);
    }

    private function treatmentMessage(string $greeting, PatientReminder $reminder, string $label): string
    {
        $medicine = trim((string) ($reminder->medicine_name ?: $reminder->title));
        $dosage = trim((string) $reminder->dosage);

        $lines = [
            $greeting,
            '',
            "Es hora de tu {$label}:",
            '• '.$medicine,
        ];

        if ($dosage !== '') {
            $lines[] = '• Dosis: '.$dosage;
        }

        if (filled($reminder->notes)) {
            $lines[] = '• Nota: '.trim((string) $reminder->notes);
        }

        $lines[] = '';
        $lines[] = 'Portal Paciente — Integracorp';

        return implode("\n", $lines);
    }
}
