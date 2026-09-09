<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ClinicalHistoryPresentation;
use Illuminate\Foundation\Http\FormRequest;

class StorePatientClinicalHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $booleanRules = [];

        foreach (array_merge(
            array_keys(ClinicalHistoryPresentation::pathologicalFlags()),
            array_keys(ClinicalHistoryPresentation::appDeclaredFlags()),
            array_keys(ClinicalHistoryPresentation::habitFlags()),
        ) as $flag) {
            $booleanRules[$flag] = ['sometimes', 'boolean'];
        }

        return array_merge($booleanRules, [
            'no_allergies' => ['sometimes', 'boolean'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'observations_allergies' => ['nullable', 'string', 'max:2000'],
            'medications_supplements' => ['nullable', 'string', 'max:2000'],
            'observations_medication' => ['nullable', 'string', 'max:2000'],
            'observations_pathological' => ['nullable', 'string', 'max:2000'],
            'observations_personal' => ['nullable', 'string', 'max:2000'],
            'observations_not_pathological' => ['nullable', 'string', 'max:2000'],
            'history_surgical' => ['nullable', 'string', 'max:2000'],
            'observations_ginecologica' => ['nullable', 'string', 'max:2000'],
            'edad_primera_menstruation' => ['nullable', 'string', 'max:50'],
            'fecha_ultima_regla' => ['nullable', 'string', 'max:50'],
            'numero_embarazos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'numero_partos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'numero_abortos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'cesareas' => ['nullable', 'integer', 'min:0', 'max:30'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $booleans = array_merge(
            ['no_allergies'],
            array_keys(ClinicalHistoryPresentation::pathologicalFlags()),
            array_keys(ClinicalHistoryPresentation::appDeclaredFlags()),
            array_keys(ClinicalHistoryPresentation::habitFlags()),
        );

        $normalized = [];

        foreach ($booleans as $key) {
            if ($this->has($key)) {
                $normalized[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
