<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Models\PatientNotificationDelivery;
use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;
use App\Services\Notifications\UltramsgClient;
use App\Support\CorporateWhatsApp;

final class WhatsAppUltramsgChannel implements NotificationChannel
{
    public function __construct(
        private readonly UltramsgClient $client,
    ) {}

    public function key(): string
    {
        return PatientNotificationDelivery::CHANNEL_WHATSAPP;
    }

    public function send(
        PatientReminder $reminder,
        TelemedicinePatient $patient,
        PatientNotificationDelivery $delivery,
        string $message,
    ): void {
        $phone = CorporateWhatsApp::normalizePhoneForWhatsApp(
            (string) ($patient->phone ?: $patient->phone_contact)
        );

        if ($phone === null) {
            $delivery->markSkipped('El paciente no tiene un teléfono válido para WhatsApp.');

            return;
        }

        $result = $this->client->sendChatMessage(
            to: $phone,
            body: $message,
            priority: 5,
            referenceId: 'reminder-'.$reminder->getKey().'-'.$delivery->getKey(),
        );

        if ($result['ok']) {
            $delivery->markSent([
                'status' => $result['status'],
                'body' => $result['body'],
            ]);

            return;
        }

        $delivery->markFailed(
            'Ultramsg rechazó o no pudo enviar el mensaje.',
            [
                'status' => $result['status'],
                'body' => $result['body'],
                'raw' => $result['raw'],
            ],
        );
    }
}
