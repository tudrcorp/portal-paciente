<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Citas médicas operativas (Integracorp / panel de operaciones TDG).
 * Tabla compartida: operation_medical_appointments.
 */
class OperationMedicalAppointment extends Model
{
    public const STATUS_SCHEDULED = 'SCHEDULED';

    public const STATUS_RESCHEDULED = 'RESCHEDULED';

    /**
     * @var list<string>
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_RESCHEDULED,
    ];

    protected $table = 'operation_medical_appointments';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'previous_appointment_at' => 'datetime',
            'last_changed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPatient(Builder $query, int|string $patientId): Builder
    {
        return $query->where('telemedicine_patient_id', $patientId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }
}
