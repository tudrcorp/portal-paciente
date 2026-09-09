<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientReminder extends Model
{
    public const TYPE_CHRONIC = 'chronic_treatment';

    public const TYPE_SPECIFIC = 'specific_treatment';

    public const TYPE_APPOINTMENT = 'appointment';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_CHRONIC,
        self::TYPE_SPECIFIC,
        self::TYPE_APPOINTMENT,
    ];

    protected $table = 'patient_reminders';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'schedule_times' => 'array',
            'schedule_days' => 'array',
            'lead_minutes' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'channel_whatsapp' => 'boolean',
            'channel_in_app' => 'boolean',
            'channel_sms' => 'boolean',
            'channel_push' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PatientNotificationDelivery::class);
    }

    /**
     * @param  Builder<PatientReminder>  $query
     * @return Builder<PatientReminder>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<PatientReminder>  $query
     * @return Builder<PatientReminder>
     */
    public function scopeForPatient(Builder $query, int|string $patientId): Builder
    {
        return $query->where('telemedicine_patient_id', $patientId);
    }

    /**
     * @return list<string>
     */
    public function enabledChannels(): array
    {
        $channels = [];

        if ($this->channel_whatsapp) {
            $channels[] = PatientNotificationDelivery::CHANNEL_WHATSAPP;
        }

        if ($this->channel_in_app) {
            $channels[] = PatientNotificationDelivery::CHANNEL_IN_APP;
        }

        if ($this->channel_sms) {
            $channels[] = PatientNotificationDelivery::CHANNEL_SMS;
        }

        if ($this->channel_push) {
            $channels[] = PatientNotificationDelivery::CHANNEL_PUSH;
        }

        return $channels;
    }

    public function belongsToPatient(TelemedicinePatient $patient): bool
    {
        return (int) $this->telemedicine_patient_id === (int) $patient->getKey();
    }
}
