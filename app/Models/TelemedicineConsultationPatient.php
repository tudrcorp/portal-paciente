<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineConsultationPatient extends Model
{
    protected $table = 'telemedicine_consultation_patients';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'feedbackOne' => 'boolean',
            'pa' => 'decimal:2',
            'fc' => 'decimal:2',
            'fr' => 'decimal:2',
            'temp' => 'decimal:2',
            'saturacion' => 'decimal:2',
            'peso' => 'decimal:2',
            'estatura' => 'decimal:2',
            'imc' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
