<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Models\PatientNotificationDelivery;
use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;

final class PushChannel implements NotificationChannel
{
    public function key(): string
    {
        return PatientNotificationDelivery::CHANNEL_PUSH;
    }

    public function send(
        PatientReminder $reminder,
        TelemedicinePatient $patient,
        PatientNotificationDelivery $delivery,
        string $message,
    ): void {
        Log::info('Push channel stub: provider not configured', [
            'reminder_id' => $reminder->getKey(),
            'patient_id' => $patient->getKey(),
            'delivery_id' => $delivery->getKey(),
        ]);

        $delivery->markSkipped('Canal Push pendiente de proveedor (FCM/APNs).', [
            'driver' => 'log',
            'message_preview' => mb_substr($message, 0, 120),
        ]);
    }
}
