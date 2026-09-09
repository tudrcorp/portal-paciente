<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineCase extends Model
{
    protected $table = 'telemedicine_cases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ambulanceParking' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
