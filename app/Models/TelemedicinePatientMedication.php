<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicinePatientMedication extends Model
{
    protected $table = 'telemedicine_patient_medications';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
