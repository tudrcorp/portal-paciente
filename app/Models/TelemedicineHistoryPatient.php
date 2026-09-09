<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemedicineHistoryPatient extends Model
{
    protected $table = 'telemedicine_history_patients';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<TelemedicinePatient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    protected function casts(): array
    {
        $flags = [
            'tension_alta', 'asma', 'cardiacos', 'gastritis_ulceras', 'enfermedad_autoimmune',
            'trombosis_embooleanas', 'fracturas', 'cancer', 'tranfusiones_sanguineas', 'tiroides',
            'hepatitis', 'moretones_frecuentes', 'psiquiatricas', 'covid', 'diabetes',
            'alteraciones_coagulacion', 'vih', 'neurologia', 'ansiedad_angustia', 'lupus',
            'diabetes_mellitus', 'presion_arterial_alta', 'tiene_cateter_venoso', 'trombosis_venosa',
            'embooleania_pulmonar', 'varices_piernas', 'insuficiencia_arterial', 'coagulacion_anormal',
            'sangrado_cirugias_previas', 'sangrado_cepillado_dental', 'alcohol', 'drogas',
            'vacunas_recientes', 'transfusiones_sanguineas', 'tension_alta_app', 'diabetes_app',
            'asma_app', 'cardiacos_app', 'gastritis_ulceras_app', 'enfermedad_autoimmune_app',
            'vih_app', 'trombosis_embooleanas_app', 'fracturas_app', 'cancer_app',
            'tranfusiones_sanguineas_app', 'tiroides_app', 'hepatitis_app', 'moretones_frecuentes_app',
            'transfusiones_sanguineas_app', 'psiquiatricas_app', 'covid_app', 'tabaco',
        ];

        return array_merge(
            array_fill_keys($flags, 'boolean'),
            [
                'numero_embarazos' => 'integer',
                'numero_partos' => 'integer',
                'numero_abortos' => 'integer',
                'cesareas' => 'integer',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
            ]
        );
    }
}
