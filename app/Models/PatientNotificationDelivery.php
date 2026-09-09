<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientNotificationDelivery extends Model
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_PUSH = 'push';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'patient_notification_deliveries';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(PatientReminder::class, 'patient_reminder_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function markSent(?array $providerResponse = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'provider_response' => $providerResponse,
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $message, ?array $providerResponse = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'sent_at' => null,
            'provider_response' => $providerResponse,
            'error_message' => $message,
        ])->save();
    }

    public function markSkipped(string $reason, ?array $providerResponse = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_SKIPPED,
            'sent_at' => null,
            'provider_response' => $providerResponse,
            'error_message' => $reason,
        ])->save();
    }
}
