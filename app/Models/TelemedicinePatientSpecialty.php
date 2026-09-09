<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicinePatientSpecialty extends Model
{
    protected $table = 'telemedicine_patient_specialties';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
