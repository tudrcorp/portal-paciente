<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Models\PatientNotificationDelivery;
use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;

final class InAppChannel implements NotificationChannel
{
    public function key(): string
    {
        return PatientNotificationDelivery::CHANNEL_IN_APP;
    }

    public function send(
        PatientReminder $reminder,
        TelemedicinePatient $patient,
        PatientNotificationDelivery $delivery,
        string $message,
    ): void {
        $delivery->markSent([
            'message' => $message,
            'title' => $reminder->title,
        ]);
    }
}
