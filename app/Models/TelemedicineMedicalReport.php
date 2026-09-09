<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineMedicalReport extends Model
{
    protected $table = 'telemedicine_medical_reports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
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
