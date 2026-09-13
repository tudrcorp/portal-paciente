<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCaseQualitySurvey extends Model
{
    protected $table = 'patient_case_quality_surveys';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'status',
        'form_url',
        'source',
        'answers',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class, 'telemedicine_case_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->completed_at !== null;
    }
}
