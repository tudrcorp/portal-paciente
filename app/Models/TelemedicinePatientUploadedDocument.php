<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemedicinePatientUploadedDocument extends Model
{
    protected $table = 'telemedicine_patient_uploaded_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class, 'telemedicine_case_id');
    }

    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function storageRelativePath(): string
    {
        return 'portal-patient-documents/'.$this->telemedicine_patient_id.'/'.ltrim((string) $this->stored_filename, '/');
    }
}
