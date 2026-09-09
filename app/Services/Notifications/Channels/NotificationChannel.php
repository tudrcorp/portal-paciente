<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Models\PatientNotificationDelivery;
use App\Models\PatientReminder;
use App\Models\TelemedicinePatient;

interface NotificationChannel
{
    public function key(): string;

    public function send(
        PatientReminder $reminder,
        TelemedicinePatient $patient,
        PatientNotificationDelivery $delivery,
        string $message,
    ): void;
}
