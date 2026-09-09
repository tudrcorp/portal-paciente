<?php

namespace App\Support;

final class ClinicalHistoryPresentation
{
    /**
     * Antecedentes clínicos (columnas tinyint del registro principal).
     *
     * @return array<string, string>
     */
    public static function pathologicalFlags(): array
    {
        return [
            'tension_alta' => __('Hipertensión arterial'),
            'presion_arterial_alta' => __('Presión arterial alta (registro)'),
            'asma' => __('Asma'),
            'cardiacos' => __('Antecedentes cardíacos'),
            'gastritis_ulceras' => __('Gastritis / úlceras'),
            'enfermedad_autoimmune' => __('Enfermedad autoinmune'),
            'trombosis_embooleanas' => __('Trombosis / embolias'),
            'trombosis_venosa' => __('Trombosis venosa'),
            'embooleania_pulmonar' => __('Embolia pulmonar'),
            'fracturas' => __('Fracturas'),
            'cancer' => __('Cáncer'),
            'tranfusiones_sanguineas' => __('Transfusiones sanguíneas (registro A)'),
            'transfusiones_sanguineas' => __('Transfusiones sanguíneas (registro B)'),
            'tiroides' => __('Tiroides'),
            'hepatitis' => __('Hepatitis'),
            'moretones_frecuentes' => __('Moretones frecuentes'),
            'psiquiatricas' => __('Antecedentes psiquiátricos'),
            'covid' => __('COVID-19'),
            'diabetes' => __('Diabetes'),
            'diabetes_mellitus' => __('Diabetes mellitus'),
            'alteraciones_coagulacion' => __('Alteraciones de coagulación'),
            'coagulacion_anormal' => __('Coagulación anormal'),
            'vih' => __('VIH'),
            'neurologia' => __('Neurología'),
            'ansiedad_angustia' => __('Ansiedad / angustia'),
            'lupus' => __('Lupus'),
            'tiene_cateter_venoso' => __('Catéter venoso'),
            'varices_piernas' => __('Varices en piernas'),
            'insuficiencia_arterial' => __('Insuficiencia arterial'),
            'sangrado_cirugias_previas' => __('Sangrado en cirugías previas'),
            'sangrado_cepillado_dental' => __('Sangrado al cepillado'),
        ];
    }

    /**
     * Declaración vía app (sufijo _app).
     *
     * @return array<string, string>
     */
    public static function appDeclaredFlags(): array
    {
        return [
            'tension_alta_app' => __('Hipertensión'),
            'diabetes_app' => __('Diabetes'),
            'asma_app' => __('Asma'),
            'cardiacos_app' => __('Cardíacos'),
            'gastritis_ulceras_app' => __('Gastritis / úlceras'),
            'enfermedad_autoimmune_app' => __('Autoinmune'),
            'vih_app' => __('VIH'),
            'trombosis_embooleanas_app' => __('Trombosis / embolias'),
            'fracturas_app' => __('Fracturas'),
            'cancer_app' => __('Cáncer'),
            'tranfusiones_sanguineas_app' => __('Transfusiones'),
            'tiroides_app' => __('Tiroides'),
            'hepatitis_app' => __('Hepatitis'),
            'moretones_frecuentes_app' => __('Moretones frecuentes'),
            'transfusiones_sanguineas_app' => __('Transfusiones (alt.)'),
            'psiquiatricas_app' => __('Psiquiátricos'),
            'covid_app' => __('COVID-19'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function habitFlags(): array
    {
        return [
            'tabaco' => __('Tabaco'),
            'alcohol' => __('Alcohol'),
            'drogas' => __('Drogas'),
            'vacunas_recientes' => __('Vacunas recientes'),
        ];
    }

    /**
     * Normaliza textos clínicos (JSON, unicode escapado, listas) para mostrarlos legibles.
     */
    public static function formatClinicalText(mixed $content): ?string
    {
        if ($content === null) {
            return null;
        }

        if (is_array($content)) {
            $items = collect($content)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values();

            return $items->isEmpty() ? null : $items->implode(', ');
        }

        $raw = trim((string) $content);
        if ($raw === '' || in_array($raw, ['[]', '{}', 'null', 'undefined', '—', '-'], true)) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                $items = collect($decoded)
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values();

                return $items->isEmpty() ? null : $items->implode(', ');
            }

            if (is_string($decoded)) {
                $decoded = trim($decoded);

                return $decoded === '' ? null : $decoded;
            }

            if ($decoded === null) {
                return null;
            }
        }

        // Decodifica secuencias unicode literales (\u00ed) si vinieran sin JSON válido.
        $unicodeDecoded = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn (array $matches): string => mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE'),
            $raw
        );

        $text = is_string($unicodeDecoded) ? trim($unicodeDecoded) : $raw;

        return $text === '' ? null : $text;
    }

    public static function hasClinicalText(mixed $content): bool
    {
        return self::formatClinicalText($content) !== null;
    }
}
