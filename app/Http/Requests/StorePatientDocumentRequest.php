<?php

namespace App\Http\Requests;

use App\Models\TelemedicinePatient;
use App\Services\PortalData\DocumentsGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof TelemedicinePatient;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('telemedicine_case_id') === '' || $this->input('telemedicine_case_id') === null) {
            $this->merge(['telemedicine_case_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $caseIds = $user instanceof TelemedicinePatient
            ? app(DocumentsGateway::class)->caseIdsFor($user)
            : [];

        return [
            'telemedicine_case_id' => ['nullable', 'integer', Rule::in($caseIds)],
            'document_name' => ['required', 'string', 'min:3', 'max:120'],
            'upload_reason' => ['required', 'string', 'min:10', 'max:500'],
            'document_file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp,gif,doc,docx',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_name.required' => __('Indica un nombre para identificar el documento.'),
            'document_name.min' => __('El nombre del documento debe tener al menos 3 caracteres.'),
            'upload_reason.required' => __('Explica brevemente por qué cargas este documento.'),
            'upload_reason.min' => __('La razón debe tener al menos 10 caracteres para que sea útil en el futuro.'),
            'document_file.required' => __('Selecciona un archivo para cargar.'),
            'document_file.max' => __('El archivo no puede superar 10 MB.'),
            'document_file.mimes' => __('Formatos permitidos: PDF, imágenes, Word (.doc, .docx).'),
            'telemedicine_case_id.in' => __('El caso seleccionado no pertenece a tu cuenta.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_name' => __('nombre del documento'),
            'upload_reason' => __('razón de la carga'),
            'document_file' => __('archivo'),
            'telemedicine_case_id' => __('caso'),
        ];
    }
}
