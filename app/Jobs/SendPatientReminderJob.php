<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PatientNotificationDelivery;
use App\Models\TelemedicinePatient;
use App\Services\Notifications\NotificationChannelManager;
use App\Services\Notifications\ReminderMessageBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPatientReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $deliveryId,
    ) {
        $this->onQueue('patient-notifications');
    }

    public function handle(
        NotificationChannelManager $channels,
        ReminderMessageBuilder $messages,
    ): void {
        $delivery = PatientNotificationDelivery::query()
            ->with('reminder')
            ->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        if ($delivery->status !== PatientNotificationDelivery::STATUS_PENDING) {
            return;
        }

        $reminder = $delivery->reminder;
        if ($reminder === null || ! $reminder->is_active) {
            $delivery->markSkipped('El recordatorio ya no está activo.');

            return;
        }

        $patient = TelemedicinePatient::query()->find($delivery->telemedicine_patient_id);
        if ($patient === null) {
            $delivery->markFailed('No se encontró el paciente asociado.');

            return;
        }

        $message = $messages->build($reminder, $patient);

        try {
            $channels->get($delivery->channel)->send($reminder, $patient, $delivery, $message);
        } catch (Throwable $exception) {
            Log::error('Failed to send patient reminder', [
                'delivery_id' => $delivery->getKey(),
                'channel' => $delivery->channel,
                'error' => $exception->getMessage(),
            ]);

            $delivery->markFailed($exception->getMessage());
        }
    }
}
